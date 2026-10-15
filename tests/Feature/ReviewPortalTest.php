<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\ReviewDecision;
use App\Events\ReviewSubmitted;
use App\Models\Brand;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Models\ReviewRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewPortalTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private string $token;

    private ReviewRequest $review;

    /** @var Post[] */
    private array $reviewPosts = [];

    private function makeReview(array $state = [], int $posts = 2): void
    {
        $this->brand = Brand::factory()->create(['requires_client_approval' => true]);
        $plan = MonthlyPlan::factory()->create(['brand_id' => $this->brand->id]);

        $this->review = new ReviewRequest([
            'brand_id' => $this->brand->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
            'expires_at' => now()->addDays(14),
            'sent_to' => ['cliente@test.com'],
        ]);
        $this->token = Str::random(40);
        $this->review->token_hash = hash('sha256', $this->token);
        $this->review->fill($state)->save();

        for ($i = 0; $i < $posts; $i++) {
            $post = Post::factory()->rendered()->create([
                'brand_id' => $this->brand->id,
                'monthly_plan_id' => $plan->id,
                'scheduled_at' => '2026-10-0'.($i + 6).' 10:00:00',
            ]);
            $this->reviewPosts[] = $post;
            $this->review->items()->create([
                'post_id' => $post->id,
                'week_from' => '2026-10-05',
            ]);
        }
    }

    private function url(string $path = ''): string
    {
        return "/review/{$this->token}{$path}";
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->get('/review/token-que-no-existe')->assertNotFound();
    }

    public function test_revoked_and_expired_show_unavailable(): void
    {
        $this->makeReview(['revoked_at' => now()]);
        $this->get($this->url())
            ->assertInertia(fn ($p) => $p->component('Review/Unavailable')->where('reason', 'revoked'));

        $this->makeReview(['expires_at' => now()->subDay()]);
        $this->get($this->url())
            ->assertInertia(fn ($p) => $p->component('Review/Unavailable')->where('reason', 'expired'));
    }

    public function test_show_renders_portal_with_posts_and_signed_media(): void
    {
        $this->makeReview();
        $this->get($this->url())
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Review/Show')
                ->has('weeks', 1)
                ->has('weeks.0.posts', 2)
                ->where('brand.name', $this->brand->name)
                ->has('weeks.0.posts.0.media.urls')
            );

        // La firma de los assets vence con la revisión.
        $response = $this->get($this->url());
        $props = $response->original->getData()['page']['props'];
        $mediaUrl = $props['weeks'][0]['posts'][0]['media']['urls'][0] ?? null;
        if ($mediaUrl) {
            $this->assertStringContainsString('expires=', $mediaUrl);
        }
    }

    public function test_approve_and_reject_item(): void
    {
        $this->makeReview();
        $item = $this->review->items()->first();

        $this->post($this->url('/items/'.$item->post_id), ['decision' => 'approved'])
            ->assertRedirect();
        $this->assertSame(ReviewDecision::Approved, $item->fresh()->decision);

        // Rechazar sin comentario → 422.
        $this->postJson($this->url('/items/'.$item->post_id), ['decision' => 'rejected'])
            ->assertStatus(422);

        $this->post($this->url('/items/'.$item->post_id), [
            'decision' => 'rejected',
            'comment' => 'Cambiar la foto',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(ReviewDecision::Rejected, $item->decision);
        $this->assertSame('Cambiar la foto', $item->comment);
        $this->assertNotNull($item->decided_at);
    }

    public function test_approve_week_marks_only_that_week(): void
    {
        $this->makeReview();
        // Un ítem en otra semana.
        $other = $this->review->items()->create([
            'post_id' => Post::factory()->rendered()->create([
                'brand_id' => $this->brand->id,
                'monthly_plan_id' => $this->reviewPosts[0]->monthly_plan_id,
                'scheduled_at' => '2026-10-20 10:00:00',
            ])->id,
            'week_from' => '2026-10-19',
        ]);

        $this->post($this->url('/weeks/2026-10-05/approve'))->assertRedirect();

        $this->assertSame(2, $this->review->items()
            ->where('week_from', '2026-10-05')->where('decision', 'approved')->count());
        $this->assertSame(ReviewDecision::Pending, $other->fresh()->decision);
    }

    public function test_approve_all_marks_everything(): void
    {
        $this->makeReview();
        $this->post($this->url('/approve-all'))->assertRedirect();
        $this->assertSame(0, $this->review->items()->where('decision', '!=', 'approved')->count());
    }

    public function test_week_comment_upserts(): void
    {
        $this->makeReview();
        $this->post($this->url('/weeks/2026-10-05/comment'), ['comment' => 'Más cortos'])
            ->assertRedirect();
        $this->post($this->url('/weeks/2026-10-05/comment'), ['comment' => 'Mejor: más cortos'])
            ->assertRedirect();

        $this->assertSame(1, $this->review->weekComments()->count());
        $this->assertSame('Mejor: más cortos', $this->review->weekComments()->first()->comment);
    }

    public function test_submit_applies_transitions_and_fires_event(): void
    {
        Event::fake();
        $this->makeReview();

        [$approvedItem, $rejectedItem] = $this->review->items()->orderBy('id')->get();
        $approvedItem->update(['decision' => 'approved']);
        $rejectedItem->update(['decision' => 'rejected', 'comment' => 'no']);

        // Este post dejó de estar rendered (el operador lo editó) → se omite.
        $this->reviewPosts[1]->update(['status' => PostStatus::Approved]);

        $this->post($this->url('/submit'), ['general_comment' => 'Todo bien'])
            ->assertRedirect();

        $this->assertSame(PostStatus::ClientApproved, $this->reviewPosts[0]->fresh()->status);
        $this->assertSame(PostStatus::Approved, $this->reviewPosts[1]->fresh()->status); // intacto

        $review = $this->review->fresh();
        $this->assertNotNull($review->submitted_at);
        $this->assertSame('Todo bien', $review->general_comment);
        Event::assertDispatched(ReviewSubmitted::class);
    }

    public function test_submit_rejected_post_goes_back_to_drafted(): void
    {
        $this->makeReview(posts: 1);
        $item = $this->review->items()->first();
        $item->update(['decision' => 'rejected', 'comment' => 'foto fea']);

        $this->post($this->url('/submit'))->assertRedirect();

        $post = $this->reviewPosts[0]->fresh();
        $this->assertSame(PostStatus::Drafted, $post->status);
        $this->assertNotNull($post->rendered_at); // conserva el render
    }

    public function test_post_after_submit_returns_409_and_show_is_readonly(): void
    {
        $this->makeReview(['submitted_at' => now()]);
        $item = $this->review->items()->first();

        $this->get($this->url())->assertOk();
        $this->post($this->url('/items/'.$item->post_id), ['decision' => 'approved'])
            ->assertStatus(409);
        $this->post($this->url('/submit'))->assertStatus(409);
    }

    public function test_find_by_token_binds_the_brand(): void
    {
        $this->makeReview();
        app()->forgetInstance('brand');

        $found = ReviewRequest::findByToken($this->token);
        $this->assertSame($this->review->id, $found->id);
        $this->assertSame($this->brand->id, app('brand')->id);
    }
}
