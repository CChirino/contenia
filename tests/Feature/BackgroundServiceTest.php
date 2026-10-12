<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Models\Post;
use App\Services\Images\BackgroundService;
use App\Services\Images\FakeGenerator;
use App\Services\Images\FakePhotoSource;
use App\Services\Images\GeometricGenerator;
use App\Services\Images\ImageGenerator;
use App\Services\Images\PhotoSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackgroundServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makePost(): Post
    {
        return Post::factory()->create([
            'network' => Network::LinkedIn,
            'format' => PostFormat::Image,
            'background_prompt' => 'oficina escolar minimalista',
            'photo_keywords' => null,
        ]);
    }

    public function test_generates_background_and_marks_source(): void
    {
        $post = $this->makePost();

        $path = app(BackgroundService::class)->for($post);

        $this->assertFileExists($path);
        $this->assertStringEndsWith("assets/{$post->id}/bg.png", $path);
        $this->assertSame('fake', $post->fresh()->background_source);
    }

    public function test_caches_background_on_second_call(): void
    {
        $post = $this->makePost();
        $service = app(BackgroundService::class);

        $first = $service->for($post);
        $mtime = filemtime($first);

        sleep(1);
        $second = $service->for($post);

        $this->assertSame($first, $second);
        $this->assertSame($mtime, filemtime($second));
    }

    public function test_fresh_regenerates(): void
    {
        $post = $this->makePost();
        $service = app(BackgroundService::class);

        $service->for($post);
        $path = $service->for($post, fresh: true);

        $this->assertFileExists($path);
    }

    public function test_falls_back_to_geometric_when_driver_fails(): void
    {
        $post = $this->makePost();

        $fake = (new FakeGenerator)->withException('pollinations caído');
        $this->app->instance(ImageGenerator::class, $fake);

        $path = app(BackgroundService::class)->for($post);

        $this->assertFileExists($path);
        $info = getimagesizefromstring(Storage::disk('local')->get("assets/{$post->id}/bg.png"));
        $this->assertSame(1200, $info[0]); // plantilla linkedin/image = 1200x660 (photoHeight)
        $this->assertSame(660, $info[1]);
        $this->assertSame('geometric', $post->fresh()->background_source);
        $this->assertSame(['source' => 'geometric'], $post->fresh()->background_meta);
    }

    public function test_uses_pixabay_when_photo_keywords_present(): void
    {
        $post = Post::factory()->create([
            'network' => Network::LinkedIn,
            'format' => PostFormat::Image,
            'photo_keywords' => 'school hallway',
        ]);

        $path = app(BackgroundService::class)->for($post);

        $post->refresh();
        $this->assertFileExists($path);
        $this->assertSame('pixabay', $post->background_source);
        $this->assertSame('pixabay', $post->background_meta['source']);
    }

    public function test_falls_back_to_image_generator_when_no_photo_found(): void
    {
        $this->app->instance(PhotoSource::class, (new FakePhotoSource)->withNull());

        $post = Post::factory()->create([
            'network' => Network::LinkedIn,
            'format' => PostFormat::Image,
            'photo_keywords' => 'zzqx nonsense',
        ]);

        $path = app(BackgroundService::class)->for($post);

        $post->refresh();
        $this->assertFileExists($path);
        $this->assertSame('fake', $post->background_source);
    }

    public function test_background_uses_photo_height_dimensions(): void
    {
        $this->app->instance(ImageGenerator::class, new GeometricGenerator);
        $post = $this->makePost();

        $path = app(BackgroundService::class)->for($post);

        [$w, $h] = getimagesize($path);
        $this->assertSame(1200, $w);
        $this->assertSame(660, $h);
    }
}
