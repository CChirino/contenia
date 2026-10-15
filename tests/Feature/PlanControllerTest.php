<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyPlan;
use App\Models\Brand;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use App\Models\Post;
use App\Models\ReviewRequest;
use App\Models\User;
use Database\Seeders\DefaultsSeeder;
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
        $this->get('/b/sabere/plans/2026/10')->assertRedirect(route('login'));
        $this->post('/b/sabere/plans/2026/10/generate')->assertRedirect(route('login'));
        $this->get('/b/sabere/plans/2026/10/status')->assertRedirect(route('login'));
    }

    public function test_show_renders_plan_page(): void
    {
        $plan = MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]);
        Post::factory()->count(2)->create(['monthly_plan_id' => $plan->id]);

        $this->actingAs(User::factory()->create())
            ->get('/b/sabere/plans/2026/10')
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
            ->post('/b/sabere/plans/2026/10/generate')
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
            ->postJson('/b/sabere/plans/2026/10/generate')
            ->assertStatus(422)
            ->assertJsonValidationErrors('generate');

        Queue::assertNotPushed(GenerateMonthlyPlan::class);
    }

    public function test_generate_fails_without_pillars(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/b/sabere/plans/2026/10/generate')
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
            ->postJson('/b/sabere/plans/2026/10/generate')
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
            ->getJson('/b/sabere/plans/2026/10/status')
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
            ->getJson('/b/sabere/plans/2026/10/status')
            ->assertJson(['pending' => true]);
    }

    public function test_dashboard_redirects_to_current_month_plan(): void
    {
        $expected = now('America/Caracas');

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertRedirect("/b/sabere/plans/{$expected->year}/{$expected->month}");
    }

    public function test_clear_error_resets_last_error(): void
    {
        $plan = MonthlyPlan::factory()->create([
            'year' => 2026,
            'month' => 10,
            'last_error' => [['from' => '2026-10-12', 'to' => '2026-10-18', 'message' => 'boom']],
        ]);

        $this->actingAs(User::factory()->create())
            ->delete('/b/sabere/plans/2026/10/error')
            ->assertRedirect();

        $this->assertNull($plan->fresh()->last_error);
    }

    public function test_clear_error_requires_auth(): void
    {
        $this->delete('/b/sabere/plans/2026/10/error')->assertRedirect('/login');
    }

    public function test_regenerate_week_dispatches_job_with_week_from(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->post('/b/sabere/plans/2026/10/weeks/2026-10-05/regenerate')
            ->assertRedirect();

        Queue::assertPushed(GenerateMonthlyPlan::class, fn ($job) => $job->weekFrom === '2026-10-05');
        $this->assertNotNull(MonthlyPlan::firstWhere('year', 2026)->generating_at);
    }

    public function test_regenerate_week_returns_409_when_generating(): void
    {
        MonthlyPlan::factory()->create([
            'year' => 2026,
            'month' => 10,
            'generating_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/b/sabere/plans/2026/10/weeks/2026-10-05/regenerate')
            ->assertStatus(409);
    }

    public function test_regenerate_week_rejects_date_outside_month(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/b/sabere/plans/2026/10/weeks/2026-11-02/regenerate')
            ->assertStatus(422);
    }

    public function test_show_includes_reviews_and_post_review_props(): void
    {
        $plan = MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]);
        $post = Post::factory()->create([
            'monthly_plan_id' => $plan->id,
            'scheduled_at' => '2026-10-06 10:00:00',
        ]);

        $review = ReviewRequest::factory()->create([
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
        ]);
        $review->items()->create([
            'post_id' => $post->id,
            'decision' => 'rejected',
            'comment' => 'Cambiar el gancho',
            'week_from' => '2026-10-05',
        ]);
        $review->weekComments()->create([
            'week_from' => '2026-10-05',
            'comment' => 'Más cortos en general',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/b/sabere/plans/2026/10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('reviews', 1)
                ->where('reviews.0.status', 'open')
                ->where('reviews.0.summary.rejected', 1)
                ->where('reviews.0.week_comments.0.comment', 'Más cortos en general')
                ->where('posts.0.review.decision', 'rejected')
                ->where('posts.0.review.comment', 'Cambiar el gancho')
            );
    }

    public function test_show_includes_quota_summary_and_allowed_networks(): void
    {
        MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]);

        $this->actingAs(User::factory()->create())
            ->get('/b/sabere/plans/2026/10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('quota.actions', 3)
                ->where('quota.unlimited', true)
                ->has('allowed_networks', 3)
            );
    }

    public function test_allowed_networks_refleja_el_plan_de_la_marca(): void
    {
        $brand = Brand::factory()->create([
            'plan_id' => Plan::factory()->create(['networks' => ['linkedin']]),
        ]);
        DefaultsSeeder::forBrand($brand);

        $this->actingAsAdminFor($brand);
        $this->get("/b/{$brand->slug}/plans/2026/10")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('allowed_networks', ['linkedin'])
            );
    }

    public function test_generate_devuelve_422_con_cupo_agotado(): void
    {
        $brand = Brand::factory()->create([
            'plan_id' => Plan::factory()->create(['posts_per_month' => 1]),
        ]);
        DefaultsSeeder::forBrand($brand);
        ContentPillar::factory()->create(['brand_id' => $brand->id, 'weight' => 100]);
        $brand->usageFor(2026, 10)->update(['posts_generated' => 1]);

        $this->actingAsAdminFor($brand);
        $this->post("/b/{$brand->slug}/plans/2026/10/generate")
            ->assertSessionHasErrors('quota');
    }
}
