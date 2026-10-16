<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostStatus;
use App\Jobs\RegeneratePost;
use App\Models\Brand;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Models\PostEdit;
use App\Models\ReviewRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostEditsTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brand = Brand::where('slug', 'sabere')->first();
    }

    private function makePost(array $attrs = []): Post
    {
        return Post::factory()->create([
            'brand_id' => $this->brand->id,
            'monthly_plan_id' => MonthlyPlan::factory()->create(['brand_id' => $this->brand->id]),
            'network' => Network::Instagram,
            'format' => 'image',
            'status' => PostStatus::Drafted,
            ...$attrs,
        ]);
    }

    private function validPayload(Post $post, array $overrides = []): array
    {
        return array_merge([
            'copy' => $post->copy,
            'hashtags' => $post->hashtags,
            'scheduled_at' => $post->scheduled_at->format('Y-m-d H:i:s'),
            'format' => $post->format->value,
        ], $overrides);
    }

    public function test_editing_hook_and_copy_records_post_edits(): void
    {
        $user = $this->actingAsOperatorFor($this->brand);
        $post = $this->makePost(['hook' => 'Hook viejo', 'copy' => 'Copy viejo']);

        $this->patch(route('posts.update', ['post' => $post->id]), $this->validPayload($post, [
            'hook' => 'Hook nuevo',
            'copy' => 'Copy nuevo',
        ]))->assertRedirect();

        $this->assertSame(2, PostEdit::count());

        $hookEdit = PostEdit::where('field', 'hook')->first();
        $this->assertSame($post->id, $hookEdit->post_id);
        $this->assertSame($user->id, $hookEdit->user_id);
        $this->assertSame('Hook viejo', $hookEdit->before);
        $this->assertSame('Hook nuevo', $hookEdit->after);

        $copyEdit = PostEdit::where('field', 'copy')->first();
        $this->assertSame('Copy viejo', $copyEdit->before);
        $this->assertSame('Copy nuevo', $copyEdit->after);
    }

    public function test_edit_links_the_open_review_request_containing_the_post(): void
    {
        $this->actingAsOperatorFor($this->brand);
        $post = $this->makePost(['copy' => 'Copy viejo']);

        $review = ReviewRequest::factory()->create(['brand_id' => $this->brand->id]);
        $review->items()->create(['post_id' => $post->id, 'week_from' => '2026-10-05']);

        // Otra revisión (más reciente pero ya enviada) también contiene el post;
        // se prefiere la abierta.
        $closed = ReviewRequest::factory()->submitted()->create(['brand_id' => $this->brand->id]);
        $closed->items()->create(['post_id' => $post->id, 'week_from' => '2026-10-12']);

        $this->patch(route('posts.update', ['post' => $post->id]), $this->validPayload($post, [
            'copy' => 'Copy nuevo',
        ]))->assertRedirect();

        $edit = PostEdit::sole();
        $this->assertSame($review->id, $edit->review_request_id);
    }

    public function test_editing_other_fields_or_same_content_records_nothing(): void
    {
        $this->actingAsOperatorFor($this->brand);
        $post = $this->makePost();

        // Mismo copy, solo cambian fecha y formato (carousel es válido en IG).
        $this->patch(route('posts.update', ['post' => $post->id]), $this->validPayload($post, [
            'scheduled_at' => '2026-12-01 09:00:00',
            'format' => 'carousel',
        ]))->assertRedirect();

        $this->assertSame(0, PostEdit::count());
    }

    public function test_regenerate_does_not_create_post_edits(): void
    {
        Queue::fake();
        $this->actingAsOperatorFor($this->brand);
        $post = $this->makePost();

        $this->post(route('posts.regenerate', ['post' => $post->id]), ['comment' => 'más corto'])
            ->assertRedirect();

        Queue::assertPushed(RegeneratePost::class);
        $this->assertSame(0, PostEdit::count());
    }

    public function test_before_and_after_are_truncated_to_1000_chars(): void
    {
        $this->actingAsOperatorFor($this->brand);
        $post = $this->makePost(['copy' => str_repeat('a', 1500)]);

        $this->patch(route('posts.update', ['post' => $post->id]), $this->validPayload($post, [
            'copy' => str_repeat('b', 1200),
        ]))->assertRedirect();

        $edit = PostEdit::sole();
        $this->assertSame(1000, mb_strlen($edit->before));
        $this->assertSame(1000, mb_strlen($edit->after));
        $this->assertStringNotContainsString('…', $edit->after);
    }
}
