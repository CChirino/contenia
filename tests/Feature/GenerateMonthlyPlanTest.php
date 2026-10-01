<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostStatus;
use App\Jobs\GenerateMonthlyPlan;
use App\Models\CalendarEvent;
use App\Models\ContentPillar;
use App\Models\LlmLog;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateMonthlyPlanTest extends TestCase
{
    use RefreshDatabase;

    private function fakeLlm(FakeLlmClient $fake): void
    {
        $this->app->instance(LlmClient::class, $fake);
    }

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

    public function test_generates_posts_week_by_week(): void
    {
        $this->seed(DefaultsSeeder::class);

        // Octubre 2026 tiene 5 semanas naturales recortadas al mes:
        // 1-4, 5-11, 12-18, 19-25, 26-31.
        $fake = (new FakeLlmClient)
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-02', 'network' => 'instagram', 'format' => 'image']),
            ]])
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-05', 'network' => 'instagram']),
                $this->postFixture(['date' => '2026-10-06', 'time' => '08:00', 'network' => 'linkedin', 'format' => 'image']),
                $this->postFixture(['date' => '2026-10-07', 'time' => '19:00', 'network' => 'tiktok', 'format' => 'video']),
            ]])
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-14', 'network' => 'instagram', 'format' => 'reel']),
                $this->postFixture(['date' => '2026-10-15', 'time' => '19:00', 'network' => 'tiktok', 'format' => 'video']),
            ]])
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-21', 'time' => '08:00', 'network' => 'linkedin', 'format' => 'image']),
            ]])
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-28', 'network' => 'instagram']),
            ]]);

        $this->fakeLlm($fake);

        GenerateMonthlyPlan::dispatchSync(2026, 10);

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(5, LlmLog::count());
        $this->assertSame(4, $plan->posts()->where('network', Network::Instagram)->count());
        $this->assertSame(2, $plan->posts()->where('network', Network::LinkedIn)->count());
        $this->assertSame(2, $plan->posts()->where('network', Network::TikTok)->count());
        $this->assertSame(8, $plan->posts()->count());

        $this->assertTrue($plan->posts()->get()->every(fn (Post $p) => $p->status === PostStatus::Drafted));
        $this->assertTrue($plan->posts()->get()->every(fn (Post $p) => $p->content_pillar_id !== null));
        $this->assertNotNull($plan->generated_at);
        $this->assertNull($plan->generating_at);
        $this->assertNull($plan->last_error);

        $this->assertSame(
            '2026-10-02 18:00',
            $plan->posts()->orderBy('scheduled_at')->first()->scheduled_at->format('Y-m-d H:i')
        );
    }

    public function test_discards_invalid_posts_and_keeps_valid(): void
    {
        $this->seed(DefaultsSeeder::class);

        CalendarEvent::factory()->create([
            'date' => '2026-10-10',
            'no_posting' => true,
        ]);

        $fake = (new FakeLlmClient)
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => [
                $this->postFixture(['network' => 'tiktok', 'format' => 'carousel']), // formato incompatible
                $this->postFixture(['date' => '2026-10-10']), // día sin publicación
                $this->postFixture(['pillar' => 'No existe']), // pilar inexistente
                $this->postFixture(['date' => '2026-10-09']), // válido
            ]])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []]);

        $this->fakeLlm($fake);

        GenerateMonthlyPlan::dispatchSync(2026, 10);

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(1, $plan->posts()->count());
        $this->assertSame(
            ContentPillar::where('name', 'Tips para docentes')->first()->id,
            $plan->posts()->first()->content_pillar_id
        );
    }

    public function test_failed_week_keeps_others_and_records_last_error(): void
    {
        $this->seed(DefaultsSeeder::class);

        $fake = (new FakeLlmClient)
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-02']),
            ]])
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-06']),
            ]])
            ->withException('rate-limited upstream')
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-20']),
            ]])
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-27']),
            ]]);

        $this->fakeLlm($fake);

        GenerateMonthlyPlan::dispatchSync(2026, 10);

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(4, $plan->posts()->count());
        $this->assertNotNull($plan->generated_at);
        $this->assertNull($plan->generating_at);
        $this->assertStringContainsString('Semana del 12/10 al 18/10', $plan->last_error);
        $this->assertStringContainsString('rate-limited upstream', $plan->last_error);
    }

    public function test_throws_when_all_weeks_fail(): void
    {
        $this->seed(DefaultsSeeder::class);

        $fake = (new FakeLlmClient)
            ->withException('fallo 1')
            ->withException('fallo 2')
            ->withException('fallo 3')
            ->withException('fallo 4')
            ->withException('fallo 5');

        $this->fakeLlm($fake);

        try {
            GenerateMonthlyPlan::dispatchSync(2026, 10);
            $this->fail('Expected LlmException');
        } catch (LlmException $e) {
            $this->assertSame('fallo 5', $e->getMessage());
        }

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();
        $this->assertStringContainsString('fallo', $plan->last_error);
        $this->assertNull($plan->generating_at);
    }

    public function test_accepts_double_encoded_posts_payload(): void
    {
        $this->seed(DefaultsSeeder::class);

        $fake = (new FakeLlmClient)
            ->withResponse(['posts' => json_encode([
                $this->postFixture(['date' => '2026-10-02']),
                $this->postFixture(['date' => '2026-10-03', 'network' => 'linkedin', 'format' => 'image', 'time' => '08:00']),
            ])])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []]);

        $this->fakeLlm($fake);

        GenerateMonthlyPlan::dispatchSync(2026, 10);

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(2, $plan->posts()->count());
        $this->assertNull($plan->last_error);
    }

    public function test_undecodable_posts_string_marks_week_and_continues(): void
    {
        $this->seed(DefaultsSeeder::class);

        $fake = (new FakeLlmClient)
            ->withResponse(['posts' => '{esto no es json'])
            ->withResponse(['posts' => [
                $this->postFixture(['date' => '2026-10-06']),
            ]])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []]);

        $this->fakeLlm($fake);

        GenerateMonthlyPlan::dispatchSync(2026, 10);

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(1, $plan->posts()->count());
        $this->assertStringContainsString('Semana del 01/10 al 04/10', $plan->last_error);
        $this->assertStringContainsString('lista de posts válida', $plan->last_error);
        $this->assertNotNull($plan->generated_at);
    }

    public function test_regeneration_deletes_only_drafted_posts(): void
    {
        $this->seed(DefaultsSeeder::class);

        $plan = MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]);
        $drafted = Post::factory()->create([
            'monthly_plan_id' => $plan->id,
            'status' => PostStatus::Drafted,
        ]);
        $approved = Post::factory()->create([
            'monthly_plan_id' => $plan->id,
            'status' => PostStatus::Approved,
        ]);

        $fake = (new FakeLlmClient)
            ->withResponse(['posts' => [$this->postFixture(['date' => '2026-10-02'])]])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []])
            ->withResponse(['posts' => []]);

        $this->fakeLlm($fake);

        GenerateMonthlyPlan::dispatchSync(2026, 10);

        $this->assertDatabaseMissing('posts', ['id' => $drafted->id]);
        $this->assertDatabaseHas('posts', ['id' => $approved->id]);
    }
}
