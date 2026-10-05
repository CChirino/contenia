<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostStatus;
use App\Jobs\RegeneratePost;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(PostStatus $status = PostStatus::Drafted, Network $network = Network::Instagram): Post
    {
        return Post::factory()->create([
            'monthly_plan_id' => MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]),
            'network' => $network,
            'format' => 'carousel',
            'status' => $status,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'copy' => 'Copy actualizado',
            'hashtags' => ['#Sabere', '#Tips'],
            'scheduled_at' => '2026-10-15 18:00:00',
            'format' => 'image',
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $post = $this->makePost();

        $this->patch("/posts/{$post->id}", [])->assertRedirect(route('login'));
        $this->post("/posts/{$post->id}/approve")->assertRedirect(route('login'));
        $this->post("/posts/{$post->id}/regenerate")->assertRedirect(route('login'));
    }

    public function test_update_modifies_post(): void
    {
        $post = $this->makePost();

        $this->actingAs(User::factory()->create())
            ->patch("/posts/{$post->id}", $this->validPayload())
            ->assertRedirect();

        $post->refresh();
        $this->assertSame('Copy actualizado', $post->copy);
        $this->assertSame(['#Sabere', '#Tips'], $post->hashtags);
        $this->assertSame('image', $post->format->value);
        $this->assertSame('2026-10-15 18:00', $post->scheduled_at->format('Y-m-d H:i'));
    }

    public function test_update_rejects_format_not_allowed_for_network(): void
    {
        $post = $this->makePost(PostStatus::Drafted, Network::LinkedIn);

        $this->actingAs(User::factory()->create())
            ->patch("/posts/{$post->id}", $this->validPayload(['format' => 'video']))
            ->assertSessionHasErrors('format');
    }

    public function test_update_rejects_post_not_in_editable_status(): void
    {
        $post = $this->makePost(PostStatus::Scheduled);

        $this->actingAs(User::factory()->create())
            ->patchJson("/posts/{$post->id}", $this->validPayload())
            ->assertStatus(422);
    }

    public function test_approve_transitions_drafted_post(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Drafted);

        $this->actingAs(User::factory()->create())
            ->post("/posts/{$post->id}/approve")
            ->assertRedirect();

        $this->assertSame(PostStatus::Approved, $post->fresh()->status);
    }

    public function test_approve_rejects_non_drafted_post(): void
    {
        $post = $this->makePost(PostStatus::Scheduled);

        $this->actingAs(User::factory()->create())
            ->postJson("/posts/{$post->id}/approve")
            ->assertStatus(422);
    }

    public function test_regenerate_dispatches_job(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Drafted);

        $this->actingAs(User::factory()->create())
            ->post("/posts/{$post->id}/regenerate", ['comment' => 'más corto'])
            ->assertRedirect();

        Queue::assertPushed(RegeneratePost::class, fn ($job) => $job->post->is($post) && $job->comment === 'más corto');
    }

    public function test_regenerate_rejects_non_drafted_post(): void
    {
        Queue::fake();
        $post = $this->makePost(PostStatus::Approved);

        $this->actingAs(User::factory()->create())
            ->postJson("/posts/{$post->id}/regenerate")
            ->assertStatus(422);

        Queue::assertNotPushed(RegeneratePost::class);
    }

    public function test_regenerate_validates_comment_length(): void
    {
        $post = $this->makePost();

        $this->actingAs(User::factory()->create())
            ->post("/posts/{$post->id}/regenerate", ['comment' => str_repeat('a', 501)])
            ->assertSessionHasErrors('comment');
    }
}
