<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CrossBrandAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_of_a_cannot_update_post_of_b(): void
    {
        $brandB = Brand::factory()->create();
        $postB = Post::factory()->create(['brand_id' => $brandB->id]);

        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $operator->brands()->attach(Brand::first());

        $this->actingAs($operator)
            ->patch("/b/sabere/posts/{$postB->id}", ['hook' => 'x'])
            ->assertNotFound();
    }

    public function test_signed_asset_url_with_wrong_brand_is_403(): void
    {
        $brandB = Brand::factory()->create();
        $postB = Post::factory()->create(['brand_id' => $brandB->id]);

        $url = URL::temporarySignedRoute('assets.show', now()->addMinute(), [
            'brand' => 'sabere',
            'post' => $postB->id,
            'file' => 'frame-01.png',
        ]);

        $this->get($url)->assertForbidden();
    }

    public function test_signed_render_frame_with_wrong_brand_is_403(): void
    {
        $brandB = Brand::factory()->create();
        $postB = Post::factory()->create(['brand_id' => $brandB->id]);

        $url = URL::temporarySignedRoute('render.frame', now()->addMinute(), [
            'brand' => 'sabere',
            'post' => $postB->id,
            'frame' => 1,
        ]);

        $this->get($url)->assertForbidden();
    }
}
