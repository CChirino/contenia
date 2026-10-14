<?php

namespace App\Jobs;

use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Images\BackgroundService;
use App\Services\Render\FrameRenderer;
use App\Services\Render\TemplateRegistry;
use App\Services\Render\VideoAssembler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
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

    public function handle(FrameRenderer $renderer, BackgroundService $backgrounds, VideoAssembler $assembler): void
    {
        $post = $this->post->fresh() ?? $this->post;

        // El job no depende de sesión: fija la marca del post.
        app()->instance('brand', $post->brand);

        $originalStatus = $post->status;

        if (! $this->force && ! in_array($originalStatus, [PostStatus::Drafted, PostStatus::Approved, PostStatus::Rendered], true)) {
            return;
        }

        try {
            $template = TemplateRegistry::for($post->network, $post->format);

            if ($template->usesAiBackground) {
                $backgrounds->for($post, $this->freshBackground);
            }

            $frames = $renderer->render($post);

            $assetPath = "assets/{$post->id}/frame-01.png";

            // Bloque B: reel/video ensambla final.mp4 a partir de los frames.
            if (in_array($post->format, [PostFormat::Reel, PostFormat::Video], true)) {
                $outDir = Storage::disk('local')->path("assets/{$post->id}");
                $framePaths = array_map(fn ($f) => "{$outDir}/{$f}", $frames);
                $assembler->assemble($framePaths, "{$outDir}/final.mp4", $template->width, $template->height);
                $assetPath = "assets/{$post->id}/final.mp4";
            }

            $post->forceFill([
                'frames' => $frames,
                'asset_path' => $assetPath,
                'rendered_at' => now(),
                'render_requested_at' => null,
                'render_error' => null,
            ]);

            if ($this->force) {
                // --force es solo depuración: no altera el estado del post.
                $post->status = $originalStatus;
            } elseif ($post->status === PostStatus::Approved) {
                // Solo los aprobados pasan a rendered; un render sobre un
                // borrador es una vista previa que conserva su estado.
                $post->status = PostStatus::Rendered;
            }

            $post->save();
        } catch (Throwable $e) {
            $post->forceFill([
                'render_error' => $e->getMessage(),
                'render_requested_at' => null,
            ])->save();

            if (! $this->force) {
                throw $e;
            }
        }
    }
}
