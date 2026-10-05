<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Models\Post;
use App\Services\Images\BackgroundService;
use App\Services\Images\FakeGenerator;
use App\Services\Images\ImageGenerator;
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
        $this->assertSame(1200, $info[0]); // plantilla linkedin/image = 1200x1200
        $this->assertSame('geometric', $post->fresh()->background_source);
    }
}
