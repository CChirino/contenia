<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\Brand;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use App\Services\Planning\PlanSchema;
use App\Services\Planning\PlanValidator;
use App\Services\Planning\PromptBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyPlan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public Brand $brand,
        public int $year,
        public int $month,
        public ?string $weekFrom = null,
    ) {}

    /**
     * Semanas naturales lunes–domingo recortadas al mes.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public static function weeksOf(int $year, int $month): array
    {
        $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'America/Caracas');
        $last = $first->endOfMonth();

        $weeks = [];
        for ($from = $first; $from <= $last; $from = $to->addDay()) {
            $to = $from->endOfWeek()->min($last)->startOfDay();
            $weeks[] = [$from, $to];
        }

        return $weeks;
    }

    public function handle(LlmClient $llm, PromptBuilder $prompts, PlanValidator $validator): void
    {
        // El job no depende de sesión: fija la marca para que el scope,
        // el perfil, pilares, estrategias y eventos queden filtrados.
        app()->instance('brand', $this->brand);

        $plan = MonthlyPlan::firstOrCreate(
            ['year' => $this->year, 'month' => $this->month],
            ['status' => 'draft']
        );

        try {
            $weeks = static::weeksOf($this->year, $this->month);

            if ($this->weekFrom) {
                $from = CarbonImmutable::parse($this->weekFrom, 'America/Caracas')->startOfDay();
                $weeks = [[$from, $from->endOfWeek()->min($weeks[count($weeks) - 1][1])->startOfDay()]];
            }

            $errors = [];
            $attempted = 0;
            $lastException = null;

            foreach ($weeks as [$from, $to]) {
                $hasPosts = $plan->posts()
                    ->whereBetween('scheduled_at', [$from, $to->endOfDay()])
                    ->exists();

                // En modo "completar mes" solo se generan las semanas vacías.
                if (! $this->weekFrom && $hasPosts) {
                    continue;
                }

                $attempted++;

                try {
                    $alreadyPlanned = $this->alreadyPlanned($plan, $from, $to);

                    ['system' => $system, 'user' => $user] = $prompts->forWeek(
                        $this->year, $this->month, $from, $to, $alreadyPlanned
                    );

                    $result = $llm->complete($system, $user, PlanSchema::monthly(), 'monthly_plan');

                    $posts = $result['posts'] ?? [];

                    if (is_string($posts)) {
                        $posts = $this->decodePostsList($posts);
                    }

                    if (! is_array($posts)) {
                        throw new LlmException('La respuesta del modelo no contiene una lista de posts válida.');
                    }

                    ['valid' => $valid] = $validator->validate($posts, $this->year, $this->month);

                    if ($valid === []) {
                        $errors[] = [
                            'from' => $from->format('Y-m-d'),
                            'to' => $to->format('Y-m-d'),
                            'message' => 'La respuesta no contenía posts válidos para la semana.',
                        ];

                        continue;
                    }

                    DB::transaction(function () use ($plan, $from, $to, $valid, &$alreadyPlanned) {
                        if ($this->weekFrom) {
                            $plan->posts()
                                ->where('status', PostStatus::Drafted)
                                ->whereBetween('scheduled_at', [$from, $to->endOfDay()])
                                ->delete();
                        }

                        foreach ($valid as $data) {
                            $post = $plan->posts()->create([
                                'scheduled_at' => CarbonImmutable::parse(
                                    "{$data['date']} {$data['time']}",
                                    'America/Caracas'
                                ),
                                'network' => $data['network'],
                                'content_pillar_id' => $data['content_pillar_id'],
                                'format' => $data['format'],
                                'hook' => $data['hook'],
                                'copy' => $data['copy'],
                                'hashtags' => $data['hashtags'],
                                'design_payload' => $data['design_payload'],
                                'background_prompt' => $data['background_prompt'] ?? null,
                                'photo_keywords' => $data['photo_keywords'] ?? null,
                                'icon' => $data['icon'] ?? null,
                                'status' => PostStatus::Drafted,
                            ]);

                            $alreadyPlanned[] = [
                                'hook' => $post->hook,
                                'pillar' => $post->contentPillar->name,
                                'network' => $post->network->value,
                            ];
                        }
                    });
                } catch (LlmException $e) {
                    $errors[] = [
                        'from' => $from->format('Y-m-d'),
                        'to' => $to->format('Y-m-d'),
                        'message' => $e->getMessage(),
                    ];
                    $lastException = $e;
                }
            }

            if ($attempted === 0) {
                $plan->update(['last_error' => null]);

                return;
            }

            if (count($errors) === $attempted) {
                $plan->update(['last_error' => $errors]);

                throw $lastException;
            }

            $plan->update([
                'generated_at' => now(),
                'last_error' => $errors === [] ? null : $errors,
            ]);
        } finally {
            $plan->forceFill(['generating_at' => null])->save();
        }
    }

    /**
     * Decodifica una lista de posts que el modelo devolvió como texto JSON.
     * Algunos modelos arrastran basura fuera del arreglo (p. ej. una llave de
     * cierre sobrante), así que se recorta al primer "[" ... último "]".
     */
    protected function decodePostsList(string $raw): mixed
    {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($raw, '[');
        $end = strrpos($raw, ']');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return json_decode(substr($raw, $start, $end - $start + 1), true);
    }

    /**
     * Posts ya planificados (mes anterior + los del propio mes, excluyendo los
     * borradores de la semana que se está regenerando) para no repetir temas.
     *
     * @return array<int, array{hook: string, pillar: string, network: string}>
     */
    protected function alreadyPlanned(MonthlyPlan $plan, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $planned = [];

        $month = CarbonImmutable::create($this->year, $this->month, 1);
        $previous = MonthlyPlan::where('year', $month->subMonth()->year)
            ->where('month', $month->subMonth()->month)
            ->first();

        if ($previous) {
            $planned = $previous->posts()
                ->with('contentPillar')
                ->orderBy('scheduled_at')
                ->get()
                ->map(fn (Post $p) => $this->postSummary($p))
                ->all();
        }

        $plan->posts()
            ->with('contentPillar')
            ->orderBy('scheduled_at')
            ->get()
            ->reject(fn (Post $p) => $p->status === PostStatus::Drafted
                && $p->scheduled_at->between($from, $to->endOfDay()))
            ->each(fn (Post $p) => $planned[] = $this->postSummary($p));

        return $planned;
    }

    /**
     * @return array{hook: string, pillar: string, network: string}
     */
    protected function postSummary(Post $post): array
    {
        return [
            'hook' => $post->hook,
            'pillar' => $post->contentPillar->name ?? 'sin pilar',
            'network' => $post->network->value,
        ];
    }
}
