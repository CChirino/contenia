<?php

namespace App\Jobs;

use App\Enums\PostStatus;
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

class GenerateMonthlyPlan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $year,
        public int $month,
    ) {}

    public function handle(LlmClient $llm, PromptBuilder $prompts, PlanValidator $validator): void
    {
        $plan = MonthlyPlan::firstOrCreate(
            ['year' => $this->year, 'month' => $this->month],
            ['status' => 'draft']
        );

        try {
            $plan->posts()->where('status', PostStatus::Drafted)->delete();

            $alreadyPlanned = $this->previouslyPlanned();

            $first = CarbonImmutable::create($this->year, $this->month, 1, 0, 0, 0, 'America/Caracas');
            $last = $first->endOfMonth();

            $errors = [];
            $weekCount = 0;
            $lastException = null;

            for ($from = $first; $from <= $last; $from = $to->addDay()) {
                $to = $from->endOfWeek()->min($last)->startOfDay();
                $weekCount++;

                try {
                    ['system' => $system, 'user' => $user] = $prompts->forWeek(
                        $this->year, $this->month, $from, $to, $alreadyPlanned
                    );

                    $result = $llm->complete($system, $user, PlanSchema::monthly(), 'monthly_plan');

                    ['valid' => $valid] = $validator->validate(
                        $result['posts'] ?? [], $this->year, $this->month
                    );

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
                            'status' => PostStatus::Drafted,
                        ]);

                        $alreadyPlanned[] = [
                            'hook' => $post->hook,
                            'pillar' => $post->contentPillar->name,
                            'network' => $post->network->value,
                        ];
                    }
                } catch (LlmException $e) {
                    $errors[] = "Semana del {$from->format('d/m')} al {$to->format('d/m')}: {$e->getMessage()}";
                    $lastException = $e;
                }
            }

            if (count($errors) === $weekCount) {
                $plan->update(['last_error' => implode("\n", $errors)]);

                throw $lastException;
            }

            $plan->update([
                'generated_at' => now(),
                'last_error' => $errors === [] ? null : implode("\n", $errors),
            ]);
        } finally {
            $plan->forceFill(['generating_at' => null])->save();
        }
    }

    /**
     * @return array<int, array{hook: string, pillar: string, network: string}>
     */
    protected function previouslyPlanned(): array
    {
        $month = CarbonImmutable::create($this->year, $this->month, 1);
        $previous = MonthlyPlan::where('year', $month->subMonth()->year)
            ->where('month', $month->subMonth()->month)
            ->first();

        if (! $previous) {
            return [];
        }

        return $previous->posts()
            ->with('contentPillar')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Post $p) => [
                'hook' => $p->hook,
                'pillar' => $p->contentPillar->name ?? 'sin pilar',
                'network' => $p->network->value,
            ])
            ->all();
    }
}
