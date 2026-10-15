<?php

namespace App\Jobs;

use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Enums\QuotaAction;
use App\Exceptions\QuotaExceededException;
use App\Models\BrandUsage;
use App\Models\Post;
use App\Services\Images\BackgroundService;
use App\Services\Quota\QuotaGuard;
use App\Services\Render\FrameRenderer;
use App\Services\Render\TemplateRegistry;
use App\Services\Render\VideoAssembler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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

        // La cuota computa contra el mes del contenido del post.
        $year = $post->monthlyPlan->year;
        $month = $post->monthlyPlan->month;
        app()->instance('usage_period', [$year, $month]);

        $originalStatus = $post->status;

        if (! $this->force && ! in_array($originalStatus, [PostStatus::Drafted, PostStatus::Approved, PostStatus::Rendered], true)) {
            return;
        }

        // Fondo nuevo: re-chequeo de cuota (concurrencia). Si el cupo se
        // agotó tras el dispatch se aborta sin renderizar.
        $quota = app(QuotaGuard::class);
        if ($this->freshBackground) {
            try {
                $quota->check($post->brand, QuotaAction::ImageRefreshes, $year, $month);
            } catch (QuotaExceededException $e) {
                $post->forceFill([
                    'render_requested_at' => null,
                    'render_error' => collect($e->errors()['quota'] ?? [])->first(),
                ])->save();

                if (! $this->force) {
                    return;
                }
            }
        }

        try {
            $template = TemplateRegistry::for($post->network, $post->format);
            $aiBackgroundUsed = false;

            if ($template->usesAiBackground) {
                $backgrounds->for($post, $this->freshBackground);
                $aiBackgroundUsed = $this->freshBackground
                    && $post->background_source === config('images.driver');
            }

            $frames = $renderer->render($post);

            $assetPath = $post->assetPath('frame-01.png');

            // Bloque B: reel/video ensambla final.mp4 a partir de los frames.
            if (in_array($post->format, [PostFormat::Reel, PostFormat::Video], true)) {
                $outDir = Storage::disk('local')->path($post->assetDir());
                $framePaths = array_map(fn ($f) => "{$outDir}/{$f}", $frames);
                $assembler->assemble($framePaths, "{$outDir}/final.mp4", $template->width, $template->height);
                $assetPath = $post->assetPath('final.mp4');
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
                // Si la marca no requiere aprobación del cliente se salta
                // el paso y queda listo para programar.
                $post->status = $post->brand->requires_client_approval
                    ? PostStatus::Rendered
                    : PostStatus::ClientApproved;
            }

            DB::transaction(function () use ($post, $aiBackgroundUsed, $quota, $year, $month) {
                $post->save();

                // Solo un fondo nuevo generado por IA consume la cuota y
                // cuenta como llamada de imagen (Pixabay/geométrico no).
                if ($aiBackgroundUsed) {
                    $quota->record($post->brand, QuotaAction::ImageRefreshes, $year, $month);
                    BrandUsage::withoutGlobalScopes()
                        ->whereKey($post->brand->usageFor($year, $month)->id)
                        ->increment('image_calls');
                }
            });
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
