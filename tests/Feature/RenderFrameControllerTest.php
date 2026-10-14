<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Jobs\RenderPost;
use App\Models\Brand;
use App\Models\Post;
use App\Services\Images\BackgroundService;
use App\Services\Images\GeometricGenerator;
use App\Services\Images\ImageGenerator;
use App\Services\Render\FrameRenderer;
use App\Services\Render\VideoAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class RenderFrameControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(?Brand $brand = null): Post
    {
        app()->instance('brand', $brand ?? Brand::where('slug', 'sabere')->firstOrFail());

        return Post::factory()->create([
            'network' => Network::LinkedIn,
            'format' => PostFormat::Image,
            'status' => PostStatus::Approved,
        ]);
    }

    private function signedUrl(Post $post, int $frame = 1): string
    {
        return URL::temporarySignedRoute('render.frame', now()->addMinute(), [
            'brand' => $post->brand->slug,
            'post' => $post->id,
            'frame' => $frame,
        ]);
    }

    public function test_frame_props_include_brand_palette_wordmark_and_logo(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Demo Co',
            'palette' => [
                'dark' => '#2B1D3A',
                'light' => '#FFF8F0',
                'accent' => '#F2A541',
                'secondary' => '#3E8E7E',
            ],
        ]);
        $post = $this->makePost($brand);

        $response = $this->get($this->signedUrl($post))->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('palette.dark', '#2B1D3A')
            ->where('palette.accent', '#F2A541')
            ->where('wordmark', 'Demo Co.')
            ->where('logoUrl', route('brands.logo', ['brand' => $brand->slug]))
        );
    }

    public function test_frame_of_other_brand_is_403(): void
    {
        $post = $this->makePost();
        $other = Brand::factory()->create();

        $url = URL::temporarySignedRoute('render.frame', now()->addMinute(), [
            'brand' => $other->slug,
            'post' => $post->id,
            'frame' => 1,
        ]);

        $this->get($url)->assertForbidden();
    }
}

/**
 * Render real por Chromium (excluido por defecto, grupo "render"): un post de
 * una marca con paleta distinta produce un PNG cuyo panel inferior usa el
 * `dark` de ESA marca. Corre con --group=render.
 */
#[Group('render')]
class RenderFrameRealPaletteTest extends TestCase
{
    public function test_render_uses_the_posts_brand_palette(): void
    {
        // Chromium pega al servidor real: apuntar a la BD real mientras dure.
        $originalDb = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => env('TEST_APP_DB', 'laravel')]);
        DB::purge('mysql');

        $this->app->instance(ImageGenerator::class, new GeometricGenerator);

        $brand = Brand::factory()->create([
            'name' => 'PaletteTest',
            'slug' => 'palette-test-'.now()->timestamp,
            'palette' => [
                'dark' => '#2B1D3A',
                'light' => '#FFF8F0',
                'accent' => '#F2A541',
                'secondary' => '#3E8E7E',
            ],
        ]);
        app()->instance('brand', $brand);

        $post = Post::factory()->create([
            'network' => Network::LinkedIn,
            'format' => PostFormat::Image,
            'status' => PostStatus::Approved,
            'photo_keywords' => null,
            'hook' => 'Test de paleta',
            'design_payload' => ['slides' => [['title' => 'Título', 'body' => 'Cuerpo.']]],
        ]);

        try {
            (new RenderPost($post))->handle(
                app(FrameRenderer::class),
                app(BackgroundService::class),
                app(VideoAssembler::class),
            );

            $post->refresh();
            $this->assertSame(PostStatus::Rendered, $post->status, $post->render_error ?? '');

            $path = Storage::disk('local')->path($post->assetPath('frame-01.png'));
            $this->assertFileExists($path);

            // El panel inferior ocupa el 45% inferior: el píxel en el centro
            // del panel debe ser el `dark` de la marca del post (#2B1D3A).
            $img = imagecreatefrompng($path);
            [$w, $h] = [imagesx($img), imagesy($img)];
            $rgb = imagecolorat($img, (int) ($w / 2), (int) ($h * 0.9));
            $color = imagecolorsforindex($img, $rgb);

            $this->assertSame(0x2B, $color['red'], 'panel rojo');
            $this->assertSame(0x1D, $color['green'], 'panel verde');
            $this->assertSame(0x3A, $color['blue'], 'panel azul');
        } finally {
            Storage::disk('local')->deleteDirectory($post->assetDir());
            $post->forceDelete();
            $brand->forceDelete();

            config(['database.connections.mysql.database' => $originalDb]);
            DB::purge('mysql');
        }
    }
}
