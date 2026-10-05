<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Jobs\RenderPost;
use App\Models\Post;
use App\Models\User;
use App\Services\Images\BackgroundService;
use App\Services\Render\FakeFrameRenderer;
use App\Services\Render\FrameRenderer;
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
        );

        $post->refresh();
        $this->assertSame(PostStatus::Rendered, $post->status);
        $this->assertSame(['frame-01.png'], $post->frames);
        $this->assertSame("assets/{$post->id}/frame-01.png", $post->asset_path);
        $this->assertNotNull($post->rendered_at);
        $this->assertNull($post->render_error);
        $this->assertSame('fake', $post->background_source);
    }

    public function test_skips_post_not_approved(): void
    {
        $post = $this->makePost(PostStatus::Drafted);

        (new RenderPost($post))->handle(
            app(FrameRenderer::class),
            app(BackgroundService::class),
        );

        $this->assertSame(PostStatus::Drafted, $post->fresh()->status);
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
            ->post(route('posts.approve', $post))
            ->assertRedirect();

        $this->assertSame(PostStatus::Approved, $post->fresh()->status);
        Queue::assertPushed(RenderPost::class);
    }

    public function test_render_endpoint_rejects_drafted(): void
    {
        $post = $this->makePost(PostStatus::Drafted);

        $this->actingAs($this->user())
            ->post(route('posts.render', $post))
            ->assertStatus(422);
    }

    public function test_render_endpoint_dispatches_for_approved(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Approved);

        $this->actingAs($this->user())
            ->post(route('posts.render', $post), ['fresh_background' => true])
            ->assertRedirect();

        Queue::assertPushed(RenderPost::class, fn ($job) => $job->freshBackground === true);
    }

    public function test_update_rendered_post_reverts_to_approved_and_redispatches(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Rendered);

        $this->actingAs($this->user())
            ->patch(route('posts.update', $post), [
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

        $this->get("/render/{$post->id}/1")->assertForbidden();
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

    private function user(): User
    {
        return User::factory()->create();
    }
}
