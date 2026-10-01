<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyPlan;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function seedPillars(): void
    {
        ContentPillar::factory()->create(['name' => 'Pilar único', 'weight' => 100]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/plans/2026/10')->assertRedirect(route('login'));
        $this->post('/plans/2026/10/generate')->assertRedirect(route('login'));
        $this->get('/plans/2026/10/status')->assertRedirect(route('login'));
    }

    public function test_show_renders_plan_page(): void
    {
        $plan = MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]);
        Post::factory()->count(2)->create(['monthly_plan_id' => $plan->id]);

        $this->actingAs(User::factory()->create())
            ->get('/plans/2026/10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Plans/Show')
                ->has('posts', 2)
                ->where('plan.posts_count', 2)
            );
    }

    public function test_generate_dispatches_job_and_marks_generating(): void
    {
        Queue::fake();
        $this->seedPillars();

        $this->actingAs(User::factory()->create())
            ->post('/plans/2026/10/generate')
            ->assertRedirect();

        Queue::assertPushed(GenerateMonthlyPlan::class);

        $plan = MonthlyPlan::where('year', 2026)->where('month', 10)->firstOrFail();
        $this->assertNotNull($plan->generating_at);
        $this->assertNull($plan->last_error);
    }

    public function test_generate_fails_when_pillar_weights_do_not_sum_100(): void
    {
        Queue::fake();
        ContentPillar::factory()->create(['weight' => 60]);

        $this->actingAs(User::factory()->create())
            ->postJson('/plans/2026/10/generate')
            ->assertStatus(422)
            ->assertJsonValidationErrors('generate');

        Queue::assertNotPushed(GenerateMonthlyPlan::class);
    }

    public function test_generate_fails_without_pillars(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/plans/2026/10/generate')
            ->assertStatus(422);

        Queue::assertNotPushed(GenerateMonthlyPlan::class);
    }

    public function test_generate_returns_409_when_already_generating(): void
    {
        Queue::fake();
        $this->seedPillars();

        MonthlyPlan::factory()->create([
            'year' => 2026,
            'month' => 10,
            'generating_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/plans/2026/10/generate')
            ->assertStatus(409);

        Queue::assertNotPushed(GenerateMonthlyPlan::class);
    }

    public function test_status_returns_json_state(): void
    {
        $plan = MonthlyPlan::factory()->create([
            'year' => 2026,
            'month' => 10,
            'generated_at' => now(),
            'generating_at' => null,
        ]);
        Post::factory()->count(3)->create(['monthly_plan_id' => $plan->id]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/plans/2026/10/status')
            ->assertOk()
            ->assertJson([
                'posts_count' => 3,
                'pending' => false,
                'last_error' => null,
            ]);

        $this->assertNotNull($response->json('generated_at'));
    }

    public function test_status_reports_pending_while_generating(): void
    {
        MonthlyPlan::factory()->create([
            'year' => 2026,
            'month' => 10,
            'generating_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/plans/2026/10/status')
            ->assertJson(['pending' => true]);
    }

    public function test_dashboard_redirects_to_current_month_plan(): void
    {
        $expected = now('America/Caracas');

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertRedirect("/plans/{$expected->year}/{$expected->month}");
    }
}
