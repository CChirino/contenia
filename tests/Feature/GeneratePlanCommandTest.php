<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyPlan;
use App\Models\MonthlyPlan;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GeneratePlanCommandTest extends TestCase
{
    use RefreshDatabase;

    private function postFixture(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-10-05',
            'time' => '18:00',
            'network' => 'instagram',
            'pillar' => 'Tips para docentes',
            'format' => 'carousel',
            'hook' => 'Hook de prueba',
            'copy' => 'Copy de prueba',
            'hashtags' => ['#Sabere'],
            'design_payload' => ['slides' => [['title' => 'T', 'body' => 'B']]],
            'background_prompt' => 'ilustración de un aula',
        ], $overrides);
    }

    public function test_generates_plan_synchronously(): void
    {
        $this->seed(DefaultsSeeder::class);

        $fake = (new FakeLlmClient)
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-02']),
            ]])
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-06', 'network' => 'linkedin', 'format' => 'image', 'time' => '08:00']),
            ]])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []]);

        $this->app->instance(LlmClient::class, $fake);

        $this->artisan('plan:generate', ['year' => 2026, 'month' => 10])
            ->assertSuccessful();

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(2, $plan->posts()->count());
    }

    public function test_queue_option_dispatches_job(): void
    {
        Queue::fake();

        $this->artisan('plan:generate', ['year' => 2026, 'month' => 10, '--queue' => true])
            ->assertSuccessful();

        Queue::assertPushed(GenerateMonthlyPlan::class);
    }

    public function test_week_option_dispatches_job_with_week_from(): void
    {
        Queue::fake();

        $this->artisan('plan:generate', [
            'year' => 2026,
            'month' => 10,
            '--queue' => true,
            '--week' => '2026-10-05',
        ])->assertSuccessful();

        Queue::assertPushed(
            GenerateMonthlyPlan::class,
            fn ($job) => $job->weekFrom === '2026-10-05'
        );
    }
}
