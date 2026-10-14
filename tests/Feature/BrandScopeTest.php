<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_filters_posts_by_active_brand(): void
    {
        $brandB = Brand::factory()->create();
        $postA = Post::factory()->create(); // marca activa (Saberé, del setUp)
        $postB = Post::factory()->create(['brand_id' => $brandB->id]);

        $this->assertTrue(Post::all()->contains($postA));
        $this->assertFalse(Post::all()->contains($postB));
    }

    public function test_create_fills_brand_id_from_active_brand(): void
    {
        $post = Post::factory()->create();

        $this->assertSame(Brand::current()->id, $post->brand_id);
    }

    public function test_without_active_brand_scope_does_not_filter(): void
    {
        $brandB = Brand::factory()->create();
        $postB = Post::factory()->create(['brand_id' => $brandB->id]);

        // Contexto de job/CLI: sin marca activa no hay filtro.
        app()->forgetInstance('brand');

        $this->assertTrue(Post::all()->contains($postB));
    }
}
