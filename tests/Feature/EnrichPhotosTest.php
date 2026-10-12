<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\LlmLog;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Services\Llm\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrichPhotosTest extends TestCase
{
    use RefreshDatabase;

    public function test_enriches_posts_without_photo_keywords(): void
    {
        $plan = MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]);
        $posts = Post::factory()->count(2)->create([
            'monthly_plan_id' => $plan->id,
            'status' => PostStatus::Drafted,
            'photo_keywords' => null,
        ]);
        $done = Post::factory()->create([
            'monthly_plan_id' => $plan->id,
            'photo_keywords' => 'ya tiene',
        ]);

        app(LlmClient::class)->withResponse([
            'items' => [
                ['id' => $posts[0]->id, 'photo_keywords' => 'school hallway lockers', 'icon' => 'fingerprint', 'background_prompt' => 'a quiet school hallway'],
                ['id' => $posts[1]->id, 'photo_keywords' => 'teacher laptop desk', 'icon' => 'no-existe', 'background_prompt' => 'a teacher desk'],
            ],
        ]);

        $this->artisan('posts:enrich-photos', ['year' => 2026, 'month' => 10])
            ->assertSuccessful();

        $posts[0]->refresh();
        $this->assertSame('school hallway lockers', $posts[0]->photo_keywords);
        $this->assertSame('fingerprint', $posts[0]->icon->value);
        $this->assertSame('a quiet school hallway', $posts[0]->background_prompt);

        $posts[1]->refresh();
        $this->assertSame('teacher laptop desk', $posts[1]->photo_keywords);
        $this->assertNull($posts[1]->icon); // icono inválido → null

        $this->assertSame('ya tiene', $done->fresh()->photo_keywords);
        $this->assertSame(1, LlmLog::count());
    }
}
