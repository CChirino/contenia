<?php

namespace Tests\Feature;

use App\Enums\MediaMode;
use App\Enums\MediaSource;
use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Jobs\RenderPost;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Services\Images\BackgroundService;
use App\Services\Media\FakeMediaInspector;
use App\Services\Media\MediaInspector;
use App\Services\Render\FakeFrameRenderer;
use App\Services\Render\FakeVideoAssembler;
use App\Services\Render\FrameRenderer;
use App\Services\Render\VideoAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RenderPostUploadTest extends TestCase
{
    use RefreshDatabase;

    private FakeVideoAssembler $assembler;

    private FakeMediaInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->assembler = new FakeVideoAssembler;
        $this->inspector = new FakeMediaInspector;
        $this->app->instance(FrameRenderer::class, new FakeFrameRenderer);
        $this->app->instance(VideoAssembler::class, $this->assembler);
        $this->app->instance(MediaInspector::class, $this->inspector);
    }

    private function makePost(Network $network, PostFormat $format, MediaMode $mode, string $mime): Post
    {
        $ext = str_starts_with($mime, 'video/') ? 'mp4' : 'png';
        $sourcePath = "brands/1/posts/tmp/source.{$ext}";

        // PNG real 20x10 para las rutas que normalizan la imagen con GD.
        $bytes = $ext === 'png'
            ? $this->tinyPng()
            : "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42mp41";
        Storage::disk('local')->put($sourcePath, $bytes);

        $post = Post::factory()->create([
            'monthly_plan_id' => MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]),
            'network' => $network,
            'format' => $format,
            'status' => PostStatus::Drafted,
            'media_source' => MediaSource::Upload,
            'media_mode' => $mode,
            'source_path' => $sourcePath,
            'source_mime' => $mime,
            'design_payload' => ['slides' => [['title' => 'T', 'body' => 'B']]],
        ]);

        // El assetDir real usa el id del post: copia el source ahí.
        $post->update(['source_path' => $post->assetPath("source.{$ext}")]);
        Storage::disk('local')->put($post->source_path, $bytes);

        return $post;
    }

    private function render(Post $post): void
    {
        (new RenderPost($post))->handle(
            app(FrameRenderer::class),
            app(BackgroundService::class),
            app(VideoAssembler::class),
        );
    }

    private function tinyPng(int $w = 20, int $h = 10): string
    {
        $im = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    public function test_foto_template_usa_la_foto_de_fondo_sin_cuota_de_imagenes(): void
    {
        $post = $this->makePost(Network::LinkedIn, PostFormat::Image, MediaMode::Template, 'image/png');

        $this->render($post);

        $post->refresh();
        Storage::disk('local')->assertExists($post->assetPath('bg.png'));
        $this->assertSame('upload', $post->background_source);
        $this->assertSame(['frame-01.png'], $post->frames);
        $this->assertSame($post->assetPath('frame-01.png'), $post->asset_path);
        $this->assertNull($post->render_error);

        // Sin cuota de imágenes ni llamada al generador.
        $this->assertSame(0, (int) $post->brand->usageFor(2026, 10)->image_refreshes);
        $this->assertSame(0, (int) $post->brand->usageFor(2026, 10)->image_calls);
    }

    public function test_foto_raw_no_usa_renderer(): void
    {
        $post = $this->makePost(Network::LinkedIn, PostFormat::Image, MediaMode::Raw, 'image/png');

        $this->render($post);

        $post->refresh();
        $this->assertSame(['frame-01.png'], $post->frames);
        Storage::disk('local')->assertExists($post->assetPath('frame-01.png'));
        // La foto ajustada no pasa por fondo IA.
        Storage::disk('local')->assertMissing($post->assetPath('bg.png'));
    }

    public function test_video_template_ensambla_portada_y_video(): void
    {
        $post = $this->makePost(Network::Instagram, PostFormat::Reel, MediaMode::Template, 'video/mp4');

        $this->render($post);

        $post->refresh();
        // Solo la portada renderizada (1 frame).
        $this->assertSame(['frame-01.png'], $post->frames);
        $this->assertSame($post->assetPath('final.mp4'), $post->asset_path);

        // El ensamblado recibió portada + source + logo null (sin PNG).
        $this->assertCount(1, $this->assembler->sourceCalls);
        $this->assertStringEndsWith('frame-01.png', $this->assembler->sourceCalls[0]['cover']);
        $this->assertStringEndsWith('source.mp4', $this->assembler->sourceCalls[0]['source']);
        $this->assertNull($this->assembler->sourceCalls[0]['logo']);

        Storage::disk('local')->assertExists($post->assetPath('final.mp4'));
        Storage::disk('local')->assertExists($post->assetPath('bg.png'));
        $this->assertSame('upload', $post->background_source);
    }

    public function test_video_raw_normaliza_y_extrae_poster(): void
    {
        $post = $this->makePost(Network::TikTok, PostFormat::Video, MediaMode::Raw, 'video/mp4');

        $this->render($post);

        $post->refresh();
        $this->assertCount(1, $this->assembler->normalizeCalls);
        $this->assertCount(1, $this->inspector->posterCalls);

        $this->assertSame(['frame-01.png'], $post->frames);
        $this->assertSame($post->assetPath('final.mp4'), $post->asset_path);
        Storage::disk('local')->assertExists($post->assetPath('final.mp4'));
        Storage::disk('local')->assertExists($post->assetPath('frame-01.png'));
    }
}
