<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostStatus;
use App\Jobs\RegeneratePost;
use App\Models\ContentPillar;
use App\Models\LlmLog;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegeneratePostTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(PostStatus $status = PostStatus::Drafted): Post
    {
        return Post::factory()->create([
            'monthly_plan_id' => MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]),
            'content_pillar_id' => ContentPillar::firstOrFail()->id,
            'network' => Network::Instagram,
            'format' => 'carousel',
            'status' => $status,
            'copy' => 'copy original',
        ]);
    }

    private function fakeLlm(array $response): FakeLlmClient
    {
        $fake = new FakeLlmClient;
        $fake->withResponse($response);
        $this->app->instance(LlmClient::class, $fake);

        return $fake;
    }

    private function response(): array
    {
        return [
            'format' => 'carousel',
            'hook' => 'hook nuevo',
            'copy' => 'copy nuevo',
            'hashtags' => ['#Nuevo'],
            'design_payload' => ['slides' => [['title' => 'X', 'body' => 'Y']]],
            'background_prompt' => 'fondo nuevo',
        ];
    }

    public function test_regenerates_drafted_post_keeping_network_date_pillar(): void
    {
        $this->seed(DefaultsSeeder::class);
        $post = $this->makePost();
        $originalDate = $post->scheduled_at->toDateTimeString();

        $this->fakeLlm($this->response());

        RegeneratePost::dispatchSync($post, 'que sea más breve');

        $post->refresh();
        $this->assertSame('copy nuevo', $post->copy);
        $this->assertSame('hook nuevo', $post->hook);
        $this->assertSame(['#Nuevo'], $post->hashtags);
        $this->assertSame(Network::Instagram, $post->network);
        $this->assertSame($originalDate, $post->scheduled_at->toDateTimeString());
        $this->assertNull($post->regeneration_error);
    }

    public function test_does_nothing_when_post_is_not_drafted(): void
    {
        $this->seed(DefaultsSeeder::class);
        $post = $this->makePost(PostStatus::Approved);

        $this->fakeLlm($this->response());

        RegeneratePost::dispatchSync($post);

        $this->assertSame('copy original', $post->fresh()->copy);
        $this->assertSame(0, LlmLog::count());
    }
}
