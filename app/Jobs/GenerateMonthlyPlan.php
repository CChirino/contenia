<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Enums\QuotaAction;
use App\Models\Brand;
use App\Models\MonthlyPlan;
use App\Models\NetworkStrategy;
use App\Models\Post;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use App\Services\Planning\PlanSchema;
use App\Services\Planning\PlanValidator;
use App\Services\Planning\PromptBuilder;
use App\Services\Quota\QuotaGuard;
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
        // La cuota y los tokens computan contra el mes del contenido.
        app()->instance('usage_period', [$this->year, $this->month]);

        $quota = app(QuotaGuard::class);

        // Generar el mes entero con el cupo agotado es un 422 explícito;
        // en regeneración semanal se recorta/salta en el bucle.
        if (! $this->weekFrom) {
            $quota->check($this->brand, QuotaAction::PostsGenerated, $this->year, $this->month);
        }

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
            $quotaNotices = [];

            foreach ($weeks as [$from, $to]) {
                $hasPosts = $plan->posts()
                    ->whereBetween('scheduled_at', [$from, $to->endOfDay()])
                    ->exists();

                // En modo "completar mes" solo se generan las semanas vacías.
                if (! $this->weekFrom && $hasPosts) {
                    continue;
                }

                // Cupo del mes de contenido: si está agotado se salta la
                // semana (sin fallar) y se anota en el aviso de la grilla.
                $remaining = $quota->remaining($this->brand, QuotaAction::PostsGenerated, $this->year, $this->month);
                if ($remaining === 0) {
                    $quotaNotices[] = "Semana del {$from->format('d/m')}: cupo de posts agotado.";

                    continue;
                }

                $attempted++;

                try {
                    $alreadyPlanned = $this->alreadyPlanned($plan, $from, $to);
                    $maxPerNetwork = $remaining === null
                        ? null
                        : $this->maxPerNetwork($remaining, $from, $to);

                    ['system' => $system, 'user' => $user] = $prompts->forWeek(
                        $this->year, $this->month, $from, $to, $alreadyPlanned, $maxPerNetwork
                    );

                    $result = $llm->complete($system, $user, PlanSchema::monthly(), 'monthly_plan');

                    $posts = $result['posts'] ?? [];

                    if (is_string($posts)) {
                        $posts = $this->decodePostsList($posts);
                    }

                    if (! is_array($posts)) {
                        throw new LlmException('La respuesta del modelo no contiene una lista de posts válida.');
                    }

                    // El validador descarta lo que exceda el cupo restante
                    // (recorte definitivo, no solo el tope del prompt).
                    ['valid' => $valid, 'rejected' => $rejected] = $validator->validate(
                        $posts, $this->year, $this->month, $remaining
                    );

                    $overQuota = collect($rejected)
                        ->filter(fn (array $r) => str_contains($r['reason'], 'excede el cupo'))
                        ->count();
                    if ($overQuota > 0) {
                        $quotaNotices[] = "Semana del {$from->format('d/m')}: {$overQuota} post(s) descartados por cupo del mes.";
                    }

                    if ($valid === []) {
                        $errors[] = [
                            'from' => $from->format('Y-m-d'),
                            'to' => $to->format('Y-m-d'),
                            'message' => 'La respuesta no contenía posts válidos para la semana.',
                        ];

                        continue;
                    }

                    DB::transaction(function () use ($plan, $from, $to, $valid, &$alreadyPlanned, $quota) {
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

                        // En la misma transacción: un fallo no consume cupo.
                        $quota->record(
                            $this->brand, QuotaAction::PostsGenerated,
                            $this->year, $this->month, count($valid)
                        );
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

                if ($lastException !== null) {
                    throw $lastException;
                }
            }

            $plan->update([
                'generated_at' => now(),
                'last_error' => $errors === [] ? null : $errors,
            ]);
        } finally {
            // Aviso persistente cuando la cuota recortó la grilla.
            $plan->forceFill([
                'quota_notice' => $quotaNotices === [] ? null : implode(' ', $quotaNotices),
                'generating_at' => null,
            ])->save();
        }
    }

    /**
     * Reparte el cupo restante entre las redes con cadencia >0 de forma
     * proporcional a su posts_per_week (restos al mayor decimal).
     *
     * @return array<string, int>
     */
    protected function maxPerNetwork(int $remaining, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $quota = app(QuotaGuard::class);
        $daysInRange = (int) $from->diffInDays($to->addDay());

        $requested = NetworkStrategy::all()
            ->filter(fn (NetworkStrategy $s) => $s->posts_per_week > 0
                && $quota->allowsNetwork($this->brand, $s->network))
            ->mapWithKeys(function (NetworkStrategy $s) use ($daysInRange) {
                $count = (int) round($s->posts_per_week * $daysInRange / 7);
                if ($daysInRange >= 3) {
                    $count = max(1, $count);
                }

                return [$s->network->value => $count];
            });

        $total = $requested->sum();
        if ($total === 0 || $total <= $remaining) {
            return $requested->all();
        }

        // Cuotas proporcionales con reparto de restos por mayor fracción.
        $caps = [];
        $fracs = [];
        $leftover = $remaining;
        foreach ($requested as $network => $count) {
            $exact = $remaining * $count / $total;
            $caps[$network] = (int) floor($exact);
            $fracs[$network] = $exact - $caps[$network];
            $leftover -= $caps[$network];
        }

        arsort($fracs);
        foreach (array_keys($fracs) as $network) {
            if ($leftover <= 0) {
                break;
            }
            $caps[$network]++;
            $leftover--;
        }

        return $caps;
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
