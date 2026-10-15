<?php

namespace Tests\Feature;

use App\Enums\ReviewDecision;
use App\Mail\ReviewRequestMail;
use App\Models\Brand;
use App\Models\BrandContact;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Models\ReviewRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewRequestStoreTest extends TestCase
{
    use RefreshDatabase;

    private function approvalBrand(): Brand
    {
        return Brand::factory()->create(['requires_client_approval' => true]);
    }

    private function renderedPost(Brand $brand, string $date): Post
    {
        $plan = MonthlyPlan::firstOrCreate(
            ['brand_id' => $brand->id, 'year' => 2026, 'month' => 10],
            ['status' => 'draft'],
        );

        return Post::factory()->rendered()->create([
            'brand_id' => $brand->id,
            'monthly_plan_id' => $plan->id,
            'scheduled_at' => $date.' 10:00:00',
        ]);
    }

    public function test_store_creates_review_with_rendered_posts_and_queues_mail(): void
    {
        Mail::fake();
        $brand = $this->approvalBrand();
        BrandContact::factory()->count(2)->create(['brand_id' => $brand->id]);
        $this->actingAsOperatorFor($brand);

        $post1 = $this->renderedPost($brand, '2026-10-05');
        $post2 = $this->renderedPost($brand, '2026-10-20');
        $drafted = Post::factory()->create([
            'brand_id' => $brand->id,
            'monthly_plan_id' => $post1->monthly_plan_id,
            'scheduled_at' => '2026-10-06 10:00:00',
        ]);

        $response = $this->post('/b/'.$brand->slug.'/plans/2026/10/reviews');

        $response->assertRedirect();
        $review = ReviewRequest::firstOrFail();

        $this->assertSame($brand->id, $review->brand_id);
        $this->assertSame('2026-10-01', $review->from_date->format('Y-m-d'));
        $this->assertSame('2026-10-31', $review->to_date->format('Y-m-d'));
        $this->assertTrue($review->expires_at->isFuture());
        $this->assertCount(2, $review->sent_to);
        $this->assertCount(2, $review->items); // el borrador no entra
        $this->assertEquals(
            [$post1->id, $post2->id],
            $review->items->sortBy('id')->pluck('post_id')->all()
        );
        $this->assertSame(ReviewDecision::Pending, $review->items->first()->decision);

        // El token en claro viaja solo una vez por flash; en BD solo el hash.
        $url = $response->getSession()->get('review_url');
        $this->assertNotNull($url);
        $token = Str::afterLast($url, '/');
        $this->assertSame(hash('sha256', $token), $review->token_hash);
        $this->assertStringNotContainsString($token, (string) $review->token_hash);

        Mail::assertQueued(ReviewRequestMail::class, 2);
    }

    public function test_store_with_week_only_includes_that_week(): void
    {
        Mail::fake();
        $brand = $this->approvalBrand();
        BrandContact::factory()->create(['brand_id' => $brand->id]);
        $this->actingAsOperatorFor($brand);

        $this->renderedPost($brand, '2026-10-06'); // semana 5–11
        $this->renderedPost($brand, '2026-10-25'); // otra semana

        $this->post('/b/'.$brand->slug.'/plans/2026/10/reviews', [
            'week_from' => '2026-10-05',
        ])->assertRedirect();

        $review = ReviewRequest::firstOrFail();
        $this->assertSame('2026-10-05', $review->from_date->format('Y-m-d'));
        $this->assertSame('2026-10-11', $review->to_date->format('Y-m-d'));
        $this->assertCount(1, $review->items);
    }

    public function test_posts_in_another_open_review_are_excluded(): void
    {
        Mail::fake();
        $brand = $this->approvalBrand();
        BrandContact::factory()->create(['brand_id' => $brand->id]);
        $this->actingAsOperatorFor($brand);

        $post = $this->renderedPost($brand, '2026-10-06');
        $open = ReviewRequest::factory()->create(['brand_id' => $brand->id]);
        $open->items()->create([
            'post_id' => $post->id,
            'week_from' => '2026-10-05',
        ]);

        // Sin más elegibles → 422.
        $this->post('/b/'.$brand->slug.'/plans/2026/10/reviews')
            ->assertSessionHasErrors('review');
        $this->assertSame(1, ReviewRequest::count());
    }

    public function test_422_when_no_eligible_posts(): void
    {
        Mail::fake();
        $brand = $this->approvalBrand();
        $this->actingAsOperatorFor($brand);

        $this->post('/b/'.$brand->slug.'/plans/2026/10/reviews')
            ->assertSessionHasErrors('review');
        Mail::assertNothingQueued();
    }

    public function test_422_when_brand_does_not_require_approval(): void
    {
        // Saberé (requires_client_approval=false) no puede crear revisiones.
        $this->actingAsOperatorFor();

        $this->post('/b/sabere/plans/2026/10/reviews')
            ->assertStatus(422);
        $this->assertSame(0, ReviewRequest::count());
    }

    public function test_rotate_renews_token_and_resends_mail(): void
    {
        Mail::fake();
        $brand = $this->approvalBrand();
        BrandContact::factory()->create(['brand_id' => $brand->id]);
        $this->actingAsOperatorFor($brand);

        $post = $this->renderedPost($brand, '2026-10-06');
        $review = ReviewRequest::factory()->create(['brand_id' => $brand->id]);
        $review->items()->create(['post_id' => $post->id, 'week_from' => '2026-10-05']);
        $oldHash = $review->token_hash;

        $response = $this->post('/b/'.$brand->slug.'/reviews/'.$review->id.'/rotate');

        $review->refresh();
        $this->assertNotSame($oldHash, $review->token_hash);
        $this->assertTrue($review->expires_at->isFuture());
        $this->assertNotNull($response->getSession()->get('review_url'));
        Mail::assertQueued(ReviewRequestMail::class);
    }

    public function test_rotate_fails_on_submitted_review(): void
    {
        $brand = $this->approvalBrand();
        $this->actingAsOperatorFor($brand);
        $review = ReviewRequest::factory()->submitted()->create(['brand_id' => $brand->id]);

        $this->post('/b/'.$brand->slug.'/reviews/'.$review->id.'/rotate')
            ->assertStatus(422);
    }

    public function test_revoke_marks_review(): void
    {
        $brand = $this->approvalBrand();
        $this->actingAsOperatorFor($brand);
        $review = ReviewRequest::factory()->create(['brand_id' => $brand->id]);

        $this->post('/b/'.$brand->slug.'/reviews/'.$review->id.'/revoke')
            ->assertRedirect();
        $this->assertNotNull($review->fresh()->revoked_at);
    }

    public function test_review_of_other_brand_is_404_for_operator(): void
    {
        $brandA = $this->approvalBrand();
        $brandB = Brand::factory()->create(['requires_client_approval' => true]);
        $this->actingAsOperatorFor($brandA);
        $review = ReviewRequest::factory()->create(['brand_id' => $brandB->id]);

        // El scope de marca hace que no exista para el operador de A.
        $this->post('/b/'.$brandA->slug.'/reviews/'.$review->id.'/revoke')
            ->assertNotFound();
    }
}
