<?php

namespace App\Providers;

use App\Models\LlmLog;
use App\Observers\LlmLogObserver;
use App\Services\Images\FakeGenerator;
use App\Services\Images\FakePhotoSource;
use App\Services\Images\GeometricGenerator;
use App\Services\Images\ImageGenerator;
use App\Services\Images\PhotoResult;
use App\Services\Images\PhotoSource;
use App\Services\Images\PixabayPhotoSource;
use App\Services\Images\PollinationsGenerator;
use App\Services\Llm\AnthropicClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Llm\OpenRouterClient;
use App\Services\Media\FakeMediaInspector;
use App\Services\Media\FfmpegMediaInspector;
use App\Services\Media\MediaInspector;
use App\Services\Render\FfmpegVideoAssembler;
use App\Services\Render\FrameRenderer;
use App\Services\Render\PlaywrightFrameRenderer;
use App\Services\Render\VideoAssembler;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LlmClient::class, function () {
            return match (config('llm.driver')) {
                'anthropic' => new AnthropicClient,
                'openrouter' => new OpenRouterClient,
                'fake' => new FakeLlmClient,
                default => throw new InvalidArgumentException(
                    'Driver LLM desconocido: '.config('llm.driver')
                ),
            };
        });

        $this->app->singleton(ImageGenerator::class, function () {
            return match (config('images.driver')) {
                'pollinations' => new PollinationsGenerator,
                'geometric' => new GeometricGenerator,
                'fake' => new FakeGenerator,
                default => throw new InvalidArgumentException(
                    'Driver de imágenes desconocido: '.config('images.driver')
                ),
            };
        });

        $this->app->singleton(PhotoSource::class, function () {
            return match (config('images.photo_source')) {
                'pixabay' => new PixabayPhotoSource,
                'fake' => new FakePhotoSource,
                'none', null => new class implements PhotoSource
                {
                    public function find(string $keywords, int $width, int $height, int $seed, int $skip = 0): ?PhotoResult
                    {
                        return null;
                    }
                },
                default => throw new InvalidArgumentException(
                    'Fuente de fotos desconocida: '.config('images.photo_source')
                ),
            };
        });

        $this->app->singleton(MediaInspector::class, function () {
            return match (config('media.inspector')) {
                'ffmpeg' => new FfmpegMediaInspector,
                'fake' => new FakeMediaInspector,
                default => throw new InvalidArgumentException(
                    'Inspector de medios desconocido: '.config('media.inspector')
                ),
            };
        });

        $this->app->bind(FrameRenderer::class, PlaywrightFrameRenderer::class);
        $this->app->bind(VideoAssembler::class, FfmpegVideoAssembler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        LlmLog::observe(LlmLogObserver::class);
    }
}
