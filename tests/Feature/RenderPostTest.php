<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Jobs\RenderPost;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Models\User;
use App\Services\Images\BackgroundService;
use App\Services\Render\FakeFrameRenderer;
use App\Services\Render\FakeVideoAssembler;
use App\Services\Render\FrameRenderer;
use App\Services\Render\VideoAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RenderPostTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(PostStatus $status, PostFormat $format = PostFormat::Image): Post
    {
        return Post::factory()->create([
            'network' => Network::LinkedIn,
            'format' => $format,
            'status' => $status,
            'background_prompt' => 'minimalist school office',
            'photo_keywords' => null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_renders_approved_post_and_marks_rendered(): void
    {
        $this->app->instance(FrameRenderer::class, new FakeFrameRenderer);
        $post = $this->makePost(PostStatus::Approved);

        (new RenderPost($post))->handle(
            app(FrameRenderer::class),
            app(BackgroundService::class),
            app(VideoAssembler::class),
        );

        $post->refresh();
        $this->assertSame(PostStatus::Rendered, $post->status);
        $this->assertSame(['frame-01.png'], $post->frames);
        $this->assertSame("assets/{$post->id}/frame-01.png", $post->asset_path);
        $this->assertNotNull($post->rendered_at);
        $this->assertNull($post->render_error);
        $this->assertSame('fake', $post->background_source);
    }

    public function test_render_on_drafted_keeps_drafted_status(): void
    {
        $this->app->instance(FrameRenderer::class, new FakeFrameRenderer);
        $post = $this->makePost(PostStatus::Drafted);

        (new RenderPost($post))->handle(
            app(FrameRenderer::class),
            app(BackgroundService::class),
            app(VideoAssembler::class),
        );

        $post->refresh();
        $this->assertSame(PostStatus::Drafted, $post->status);
        $this->assertSame(['frame-01.png'], $post->frames);
        $this->assertNotNull($post->rendered_at);
    }

    public function test_skips_post_not_renderable(): void
    {
        $post = $this->makePost(PostStatus::Scheduled);

        (new RenderPost($post))->handle(
            app(FrameRenderer::class),
            app(BackgroundService::class),
            app(VideoAssembler::class),
        );

        $this->assertSame(PostStatus::Scheduled, $post->fresh()->status);
        $this->assertNull($post->fresh()->frames);
    }

    public function test_render_failure_records_error_and_keeps_status(): void
    {
        $this->app->instance(FrameRenderer::class, (new FakeFrameRenderer)->withException('chromium explotó'));
        $post = $this->makePost(PostStatus::Approved);

        try {
            (new RenderPost($post))->handle(
                app(FrameRenderer::class),
                app(BackgroundService::class),
                app(VideoAssembler::class),
            );
        } catch (\Throwable) {
        }

        $post->refresh();
        $this->assertSame(PostStatus::Approved, $post->status);
        $this->assertSame('chromium explotó', $post->render_error);
    }

    public function test_force_renders_drafted_without_changing_status(): void
    {
        $this->app->instance(FrameRenderer::class, new FakeFrameRenderer);
        $post = $this->makePost(PostStatus::Drafted);

        (new RenderPost($post, force: true))->handle(
            app(FrameRenderer::class),
            app(BackgroundService::class),
            app(VideoAssembler::class),
        );

        $post->refresh();
        $this->assertSame(PostStatus::Drafted, $post->status);
        $this->assertSame(['frame-01.png'], $post->frames);
    }

    public function test_approve_dispatches_render_job(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Drafted);

        $this->actingAs($this->user())
            ->post(route('posts.approve', ['post' => $post]))
            ->assertRedirect();

        $this->assertSame(PostStatus::Approved, $post->fresh()->status);
        Queue::assertPushed(RenderPost::class);
    }

    public function test_render_endpoint_rejects_scheduled(): void
    {
        $post = $this->makePost(PostStatus::Scheduled);

        $this->actingAs($this->user())
            ->post(route('posts.render', ['post' => $post]))
            ->assertStatus(422);
    }

    public function test_render_endpoint_dispatches_for_drafted(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Drafted);

        $this->actingAs($this->user())
            ->post(route('posts.render', ['post' => $post]))
            ->assertRedirect();

        Queue::assertPushed(RenderPost::class);
    }

    public function test_approve_with_fresh_render_goes_straight_to_rendered(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Drafted);
        $post->update(['rendered_at' => now()]);

        $this->actingAs($this->user())
            ->post(route('posts.approve', ['post' => $post]))
            ->assertRedirect();

        $this->assertSame(PostStatus::Rendered, $post->fresh()->status);
        Queue::assertNotPushed(RenderPost::class);
    }

    public function test_render_endpoint_dispatches_for_approved(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Approved);

        $this->actingAs($this->user())
            ->post(route('posts.render', ['post' => $post]), ['fresh_background' => true])
            ->assertRedirect();

        Queue::assertPushed(RenderPost::class, fn ($job) => $job->freshBackground === true);
    }

    public function test_update_rendered_post_reverts_to_approved_and_redispatches(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Rendered);
        $post->update(['rendered_at' => now()]);

        $this->actingAs($this->user())
            ->patch(route('posts.update', ['post' => $post]), [
                'copy' => 'nuevo copy',
                'hashtags' => ['#a'],
                'scheduled_at' => '2026-10-05 09:00',
                'format' => 'image',
            ])
            ->assertRedirect();

        $this->assertSame(PostStatus::Approved, $post->fresh()->status);
        Queue::assertPushed(RenderPost::class);
    }

    public function test_render_frame_requires_signature(): void
    {
        $post = $this->makePost(PostStatus::Rendered);

        $this->get("/render/sabere/{$post->id}/1")->assertForbidden();
    }

    public function test_assets_show_rejects_unknown_filename(): void
    {
        $post = $this->makePost(PostStatus::Rendered);

        $url = URL::temporarySignedRoute('assets.show', now()->addMinute(), [
            'post' => $post->id,
            'file' => 'evil.php',
        ]);

        $this->get($url)->assertNotFound();
    }

    public function test_assets_show_serves_signed_file(): void
    {
        $post = $this->makePost(PostStatus::Rendered);
        Storage::disk('local')->put("assets/{$post->id}/frame-01.png", 'png-bytes');

        $url = URL::temporarySignedRoute('assets.show', now()->addMinute(), [
            'post' => $post->id,
            'file' => 'frame-01.png',
        ]);

        $this->get($url)->assertOk();
    }

    public function test_download_single_frame_returns_png(): void
    {
        $post = $this->makePost(PostStatus::Rendered);
        $post->update(['frames' => ['frame-01.png']]);
        Storage::disk('local')->put("assets/{$post->id}/frame-01.png", 'png-bytes');

        $this->actingAs($this->user())
            ->get(route('posts.download', ['post' => $post]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_download_multiple_frames_returns_zip(): void
    {
        $post = $this->makePost(PostStatus::Rendered);
        $post->update(['frames' => ['frame-01.png', 'frame-02.png']]);
        Storage::disk('local')->put("assets/{$post->id}/frame-01.png", 'a');
        Storage::disk('local')->put("assets/{$post->id}/frame-02.png", 'b');

        $response = $this->actingAs($this->user())
            ->get(route('posts.download', ['post' => $post]))
            ->assertOk();

        $this->assertSame('application/zip', $response->headers->get('content-type'));
    }

    public function test_download_without_frames_returns_404(): void
    {
        $post = $this->makePost(PostStatus::Drafted);

        $this->actingAs($this->user())
            ->get(route('posts.download', ['post' => $post]))
            ->assertNotFound();
    }

    public function test_video_post_assembles_final_mp4(): void
    {
        $this->app->instance(FrameRenderer::class, new FakeFrameRenderer);
        $assembler = new FakeVideoAssembler;
        $this->app->instance(VideoAssembler::class, $assembler);

        $post = $this->makePost(PostStatus::Approved, PostFormat::Video);
        $post->update(['network' => Network::TikTok]);

        (new RenderPost($post))->handle(
            app(FrameRenderer::class),
            app(BackgroundService::class),
            app(VideoAssembler::class),
        );

        $post->refresh();
        $this->assertSame(PostStatus::Rendered, $post->status);
        $this->assertSame("assets/{$post->id}/final.mp4", $post->asset_path);
        $this->assertCount(1, $assembler->calls);
        $this->assertCount(count($post->frames), $assembler->calls[0]['frames']);
        $this->assertSame([1080, 1920], [$assembler->calls[0]['width'], $assembler->calls[0]['height']]);
    }

    public function test_image_post_does_not_invoke_assembler(): void
    {
        $this->app->instance(FrameRenderer::class, new FakeFrameRenderer);
        $assembler = new FakeVideoAssembler;
        $this->app->instance(VideoAssembler::class, $assembler);

        $post = $this->makePost(PostStatus::Approved, PostFormat::Image);

        (new RenderPost($post))->handle(
            app(FrameRenderer::class),
            app(BackgroundService::class),
            app(VideoAssembler::class),
        );

        $this->assertCount(0, $assembler->calls);
        $this->assertSame("assets/{$post->id}/frame-01.png", $post->fresh()->asset_path);
    }

    public function test_download_video_returns_mp4(): void
    {
        $post = $this->makePost(PostStatus::Rendered, PostFormat::Video);
        $post->update(['network' => Network::TikTok]);
        Storage::disk('local')->put("assets/{$post->id}/final.mp4", 'mp4-bytes');
        Storage::disk('local')->put("assets/{$post->id}/frame-01.png", 'png');

        $response = $this->actingAs($this->user())
            ->get(route('posts.download', ['post' => $post]))
            ->assertOk();

        $this->assertSame('video/mp4', $response->headers->get('content-type'));
    }

    public function test_plan_status_includes_rendering_ids(): void
    {
        $plan = MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]);
        $rendering = Post::factory()->create([
            'monthly_plan_id' => $plan->id,
            'status' => PostStatus::Drafted,
            'rendered_at' => null,
            'render_requested_at' => now(),
        ]);
        Post::factory()->create([
            'monthly_plan_id' => $plan->id,
            'status' => PostStatus::Rendered,
            'rendered_at' => now(),
            'render_requested_at' => null,
        ]);

        $response = $this->actingAs($this->user())
            ->getJson(route('plans.status', ['year' => 2026, 'month' => 10]))
            ->assertOk();

        $this->assertSame([$rendering->id], $response->json('rendering_ids'));
    }

    private function user(): User
    {
        return User::factory()->create();
    }
}
