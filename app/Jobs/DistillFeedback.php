<?php

namespace App\Jobs;

use App\Enums\GuidelineSource;
use App\Enums\GuidelineStatus;
use App\Enums\ReviewDecision;
use App\Models\Brand;
use App\Models\BrandGuideline;
use App\Models\PostEdit;
use App\Models\ReviewRequest;
use App\Services\Llm\LlmClient;
use App\Services\Onboarding\OnboardingSchema;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Memoria de la marca (spec §8.2): destila la evidencia del periodo
 * (rechazos y comentarios del cliente, ediciones del operador) en
 * pautas. Nuevas → pending; reforzadas → +evidencia/peso; retiradas →
 * pending_retire (pending → discarded). Nada se activa sin operador.
 */
class DistillFeedback implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public Brand $brand,
        public CarbonInterface|string $from,
        public CarbonInterface|string $to,
    ) {}

    public function handle(LlmClient $llm): void
    {
        // El job no depende de sesión: fija la marca para el scope y los logs.
        app()->instance('brand', $this->brand);

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        $evidence = $this->collectEvidence($from, $to);

        if ($evidence['lines'] === []) {
            return; // sin evidencia no hay nada que destilar (ni llamada LLM)
        }

        $guidelines = BrandGuideline::whereIn('status', [
            GuidelineStatus::Pending,
            GuidelineStatus::Active,
        ])->orderBy('id')->get();

        $result = $llm->complete(
            $this->systemPrompt(),
            $this->userPrompt($guidelines, $evidence['lines'], $from, $to),
            OnboardingSchema::feedback(),
            'distill_feedback',
        );

        $this->apply($result, $evidence['hasPostEdits'], $from, $to);
    }

    /**
     * Evidencia del periodo: solicitudes de revisión que solapan el rango
     * (rechazos con comentario, comentarios por semana, comentario general)
     * y ediciones del operador a posts de la marca.
     *
     * @return array{lines: array<int, string>, hasPostEdits: bool, reviewIds: array<int, int>}
     */
    private function collectEvidence(Carbon $from, Carbon $to): array
    {
        $requests = ReviewRequest::with([
            'items' => fn ($q) => $q
                ->where('decision', ReviewDecision::Rejected)
                ->with('post'),
            'weekComments',
        ])
            ->where('from_date', '<=', $to->toDateString())
            ->where('to_date', '>=', $from->toDateString())
            ->orderBy('id')
            ->get();

        $lines = [];

        foreach ($requests as $request) {
            foreach ($request->items as $item) {
                if (! filled($item->comment)) {
                    continue;
                }
                $hook = Str::limit((string) $item->post?->hook, 80, '…');
                $lines[] = "El cliente rechazó un post (\"{$hook}\"): {$item->comment}";
            }
            foreach ($request->weekComments as $comment) {
                $lines[] = "Comentario del cliente sobre la semana del {$comment->week_from->format('d/m/Y')}: {$comment->comment}";
            }
            if (filled($request->general_comment)) {
                $lines[] = "Comentario general del cliente: {$request->general_comment}";
            }
        }

        $reviewIds = $requests->pluck('id')->all();

        $edits = PostEdit::whereHas('post', fn ($q) => $q
            ->withoutGlobalScopes()
            ->where('brand_id', $this->brand->id))
            ->where(function ($q) use ($from, $to, $reviewIds) {
                $q->whereBetween('created_at', [$from, $to]);
                if ($reviewIds !== []) {
                    $q->orWhereIn('review_request_id', $reviewIds);
                }
            })
            ->orderBy('id')
            ->get();

        foreach ($edits as $edit) {
            $lines[] = "El operador cambió el {$edit->field} de \""
                .Str::limit((string) $edit->before, 80, '…')
                .'" a "'.Str::limit((string) $edit->after, 80, '…').'"';
        }

        return [
            'lines' => $lines,
            'hasPostEdits' => $edits->isNotEmpty(),
            'reviewIds' => $reviewIds,
        ];
    }

    private function systemPrompt(): string
    {
        return 'Eres el director de estrategia de una agencia de contenido. A partir del feedback '
            .'del cliente y de las correcciones del equipo sobre los posts de una marca, destilas '
            .'pautas duraderas ("guidelines") que mejoren las próximas generaciones. Las pautas son '
            .'concretas y accionables (≤200 caracteres), no quejas puntuales. Respondes en español '
            .'y solo propones retirar una pauta cuando la evidencia la contradice o la vuelve obsoleta.';
    }

    /**
     * @param  Collection<int, BrandGuideline>  $guidelines
     * @param  array<int, string>  $lines
     */
    private function userPrompt($guidelines, array $lines, Carbon $from, Carbon $to): string
    {
        $parts = [
            "Marca: {$this->brand->name}",
            "Periodo de feedback: {$from->format('d/m/Y')} – {$to->format('d/m/Y')}",
        ];

        if ($guidelines->isNotEmpty()) {
            $parts[] = "Pautas existentes (pendientes y activas):\n".$guidelines
                ->map(fn (BrandGuideline $g) => "- #{$g->id} [{$g->status->value}, peso {$g->weight}, "
                    ."{$g->evidence_count} evidencias] {$g->text}")
                ->implode("\n");
        } else {
            $parts[] = 'La marca aún no tiene pautas.';
        }

        $parts[] = "Evidencia del periodo:\n- ".implode("\n- ", $lines);
        $parts[] = 'Emite con emit_result: new (pautas nuevas, ≤200 chars), reinforce (ids de pautas '
            .'existentes que la evidencia confirma) y retire ({id, reason} para las que la evidencia '
            .'contradice). Usa solo ids de la lista anterior.';

        return implode("\n\n", $parts);
    }

    /**
     * Aplica el destilado: nuevas → pending, reinforce → +evidencia/peso,
     * retire → pending_retire (o discarded si aún era pending). Los ids de
     * otra marca no llegan: el scope de marca los filtra.
     *
     * @param  array<string, mixed>  $result
     */
    private function apply(array $result, bool $hasPostEdits, Carbon $from, Carbon $to): void
    {
        // Si toda la evidencia viene del cliente (sin ediciones del
        // operador) la fuente es client_feedback; si no, ai_distilled.
        $source = $hasPostEdits ? GuidelineSource::AiDistilled : GuidelineSource::ClientFeedback;

        foreach ($result['new'] ?? [] as $item) {
            $text = Str::limit(trim((string) ($item['text'] ?? '')), 200, '');
            if ($text === '') {
                continue;
            }

            BrandGuideline::create([
                'brand_id' => $this->brand->id,
                'text' => $text,
                'source' => $source,
                'status' => GuidelineStatus::Pending,
                'weight' => 1,
                'evidence_count' => 1,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
            ]);
        }

        $reinforceIds = collect($result['reinforce'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($reinforceIds !== []) {
            BrandGuideline::whereIn('id', $reinforceIds)
                ->whereIn('status', [GuidelineStatus::Pending, GuidelineStatus::Active])
                ->get()
                ->each(function (BrandGuideline $g) {
                    $g->increment('evidence_count');
                    $g->update(['weight' => min($g->weight + 1, 10)]);
                });
        }

        $retireIds = collect($result['retire'] ?? [])
            ->map(fn ($r) => (int) (is_array($r) ? ($r['id'] ?? 0) : $r))
            ->filter()
            ->all();

        BrandGuideline::whereIn('id', $retireIds)->get()
            ->each(function (BrandGuideline $g) {
                $g->update(['status' => match ($g->status) {
                    GuidelineStatus::Active => GuidelineStatus::PendingRetire,
                    GuidelineStatus::Pending => GuidelineStatus::Discarded,
                    default => $g->status,
                }]);
            });
    }
}
