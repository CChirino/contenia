<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\MonthlyPlan;
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

    public int $timeout = 300;

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

            ['system' => $system, 'user' => $user] = $prompts->forMonth($this->year, $this->month);

            $result = $llm->complete($system, $user, PlanSchema::monthly(), 'monthly_plan');

            ['valid' => $valid] = $validator->validate($result['posts'] ?? [], $this->year, $this->month);

            foreach ($valid as $data) {
                $plan->posts()->create([
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
            }

            $plan->update(['generated_at' => now(), 'last_error' => null]);
        } catch (LlmException $e) {
            $plan->update(['last_error' => $e->getMessage()]);

            throw $e;
        } finally {
            $plan->forceFill(['generating_at' => null])->save();
        }
    }
}
