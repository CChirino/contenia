<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Images\BackgroundService;
use App\Services\Render\FrameRenderer;
use App\Services\Render\TemplateRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RenderPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public Post $post,
        public bool $freshBackground = false,
        public bool $force = false,
    ) {}

    public function handle(FrameRenderer $renderer, BackgroundService $backgrounds): void
    {
        $post = $this->post->fresh() ?? $this->post;
        $originalStatus = $post->status;

        if (! $this->force && ! in_array($originalStatus, [PostStatus::Approved, PostStatus::Rendered], true)) {
            return;
        }

        try {
            $template = TemplateRegistry::for($post->network, $post->format);

            if ($template->usesAiBackground) {
                $backgrounds->for($post, $this->freshBackground);
            }

            $frames = $renderer->render($post);

            $post->forceFill([
                'frames' => $frames,
                'asset_path' => "assets/{$post->id}/frame-01.png",
                'rendered_at' => now(),
                'render_error' => null,
            ]);

            if ($this->force) {
                // --force es solo depuración: no altera el estado del post.
                $post->status = $originalStatus;
            } elseif ($post->status !== PostStatus::Rendered) {
                if (! $post->status->canTransitionTo(PostStatus::Rendered)) {
                    throw new \RuntimeException(
                        "No se puede pasar de {$post->status->value} a rendered."
                    );
                }
                $post->status = PostStatus::Rendered;
            }

            $post->save();
        } catch (Throwable $e) {
            $post->forceFill(['render_error' => $e->getMessage()])->save();

            if (! $this->force) {
                throw $e;
            }
        }
    }
}
