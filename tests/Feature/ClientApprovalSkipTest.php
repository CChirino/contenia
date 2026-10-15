<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Jobs\RenderPost;
use App\Models\Brand;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Services\Render\FakeFrameRenderer;
use App\Services\Render\FakeVideoAssembler;
use App\Services\Render\FrameRenderer;
use App\Services\Render\VideoAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientApprovalSkipTest extends TestCase
{
    use RefreshDatabase;

    private function postFor(Brand $brand, PostStatus $status): Post
    {
        $plan = MonthlyPlan::factory()->create(['brand_id' => $brand->id]);

        return Post::factory()->create([
            'brand_id' => $brand->id,
            'monthly_plan_id' => $plan->id,
            'status' => $status,
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function test_render_post_skips_review_when_brand_does_not_require_approval(): void
    {
        // Saberé no requiere aprobación del cliente.
        app()->instance(FrameRenderer::class, new FakeFrameRenderer);
        app()->instance(VideoAssembler::class, new FakeVideoAssembler);
        Storage::fake('local');

        $brand = Brand::where('slug', 'sabere')->firstOrFail();
        $this->assertFalse($brand->requires_client_approval);
        app()->instance('brand', $brand);

        $post = $this->postFor($brand, PostStatus::Approved);

        RenderPost::dispatchSync($post);

        $this->assertSame(PostStatus::ClientApproved, $post->fresh()->status);
    }

    public function test_render_post_stays_rendered_when_brand_requires_approval(): void
    {
        app()->instance(FrameRenderer::class, new FakeFrameRenderer);
        app()->instance(VideoAssembler::class, new FakeVideoAssembler);
        Storage::fake('local');

        $brand = Brand::factory()->create(['requires_client_approval' => true]);
        app()->instance('brand', $brand);

        $post = $this->postFor($brand, PostStatus::Approved);

        RenderPost::dispatchSync($post);

        $this->assertSame(PostStatus::Rendered, $post->fresh()->status);
    }

    public function test_approve_with_fresh_render_goes_to_client_approved(): void
    {
        $brand = Brand::where('slug', 'sabere')->firstOrFail();
        $this->actingAsAdminFor($brand);

        $post = $this->postFor($brand, PostStatus::Drafted);
        $post->forceFill(['rendered_at' => now(), 'updated_at' => now()->subMinute()])->save();

        $this->post(route('posts.approve', ['brand' => $brand->slug, 'post' => $post->id]))
            ->assertRedirect();

        $this->assertSame(PostStatus::ClientApproved, $post->fresh()->status);
    }

    public function test_brand_prop_carries_requires_client_approval(): void
    {
        $brand = Brand::where('slug', 'sabere')->firstOrFail();
        $this->actingAsAdminFor($brand);

        $this->get('/b/sabere/plans/2026/10')
            ->assertInertia(fn ($page) => $page
                ->where('brand.requires_client_approval', false)
            );
    }
}
