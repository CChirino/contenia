<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostStatus;
use App\Jobs\GenerateMonthlyPlan;
use App\Models\CalendarEvent;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateMonthlyPlanTest extends TestCase
{
    use RefreshDatabase;

    private function fakeLlm(array $response): void
    {
        $fake = new FakeLlmClient;
        $fake->withResponse($response);
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

    public function test_generates_posts_for_each_network(): void
    {
        $this->seed(DefaultsSeeder::class);

        $this->fakeLlm(['posts' => [
            $this->postFixture(['date' => '2026-10-05', 'network' => 'instagram']),
            $this->postFixture(['date' => '2026-10-08', 'network' => 'instagram', 'format' => 'reel']),
            $this->postFixture(['date' => '2026-10-06', 'time' => '08:00', 'network' => 'linkedin', 'format' => 'image']),
            $this->postFixture(['date' => '2026-10-09', 'time' => '08:00', 'network' => 'linkedin', 'format' => 'image']),
            $this->postFixture(['date' => '2026-10-07', 'time' => '19:00', 'network' => 'tiktok', 'format' => 'video']),
            $this->postFixture(['date' => '2026-10-10', 'time' => '19:00', 'network' => 'tiktok', 'format' => 'video']),
        ]]);

        GenerateMonthlyPlan::dispatchSync(2026, 10);

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(2, $plan->posts()->where('network', Network::Instagram)->count());
        $this->assertSame(2, $plan->posts()->where('network', Network::LinkedIn)->count());
        $this->assertSame(2, $plan->posts()->where('network', Network::TikTok)->count());

        $this->assertTrue($plan->posts()->get()->every(fn (Post $p) => $p->status === PostStatus::Drafted));
        $this->assertTrue($plan->posts()->get()->every(fn (Post $p) => $p->content_pillar_id !== null));
        $this->assertNotNull($plan->generated_at);
        $this->assertNull($plan->generating_at);

        $this->assertSame(
            '2026-10-05 18:00',
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

        $this->fakeLlm(['posts' => [
            $this->postFixture(['network' => 'tiktok', 'format' => 'carousel']), // formato incompatible
            $this->postFixture(['date' => '2026-10-10']), // día sin publicación
            $this->postFixture(['pillar' => 'No existe']), // pilar inexistente
            $this->postFixture(), // válido
        ]]);

        GenerateMonthlyPlan::dispatchSync(2026, 10);

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(1, $plan->posts()->count());
        $this->assertSame(
            ContentPillar::where('name', 'Tips para docentes')->first()->id,
            $plan->posts()->first()->content_pillar_id
        );
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

        $this->fakeLlm(['posts' => [$this->postFixture()]]);

        GenerateMonthlyPlan::dispatchSync(2026, 10);

        $this->assertDatabaseMissing('posts', ['id' => $drafted->id]);
        $this->assertDatabaseHas('posts', ['id' => $approved->id]);
    }
}
