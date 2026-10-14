<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateAssetsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_moves_asset_dirs_to_brand_layout_and_rewrites_asset_path(): void
    {
        Storage::fake('local');
        $post = Post::factory()->create();

        Storage::disk('local')->put("assets/{$post->id}/bg.png", 'bg');
        Storage::disk('local')->put("assets/{$post->id}/frame-01.png", 'f1');
        Storage::disk('local')->put("assets/{$post->id}/frame-02.png", 'f2');
        // Directorio huérfano: se deja intacto.
        Storage::disk('local')->put('assets/999999/frame-01.png', 'x');

        $post->update(['asset_path' => "assets/{$post->id}/frame-01.png"]);

        $this->artisan('brands:migrate-assets')->assertSuccessful();

        $target = $post->assetDir();
        Storage::disk('local')->assertExists("{$target}/bg.png");
        Storage::disk('local')->assertExists("{$target}/frame-01.png");
        Storage::disk('local')->assertExists("{$target}/frame-02.png");
        Storage::disk('local')->assertMissing("assets/{$post->id}/bg.png");
        Storage::disk('local')->assertExists('assets/999999/frame-01.png');

        $this->assertSame("{$target}/frame-01.png", $post->fresh()->asset_path);
    }

    public function test_is_idempotent(): void
    {
        Storage::fake('local');
        $post = Post::factory()->create();

        Storage::disk('local')->put("assets/{$post->id}/frame-01.png", 'f1');

        $this->artisan('brands:migrate-assets')->assertSuccessful();
        $this->artisan('brands:migrate-assets')
            ->expectsOutputToContain('No hay directorios')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($post->assetPath('frame-01.png'));
    }

    public function test_new_assets_write_to_brand_layout(): void
    {
        Storage::fake('local');
        $post = Post::factory()->create();

        Storage::disk('local')->put($post->assetPath('bg.png'), 'bg');

        Storage::disk('local')->assertExists("brands/{$post->brand_id}/posts/{$post->id}/bg.png");
    }
}
