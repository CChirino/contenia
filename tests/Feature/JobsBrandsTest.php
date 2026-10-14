<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyPlan;
use App\Models\Brand;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobsBrandsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El job fija la marca del modelo: plan, posts y pilares usan los datos
     * de la marca indicada aunque haya otra marca "activa" en el contexto.
     */
    public function test_generate_monthly_plan_uses_its_own_brand(): void
    {
        $brandB = Brand::factory()->create();
        DefaultsSeeder::forBrand($brandB);
        $pillar = ContentPillar::factory()->create([
            'brand_id' => $brandB->id,
            'name' => 'Pilar B',
            'weight' => 100,
        ]);

        $fake = (new FakeLlmClient)->withResponse(['posts' => [
            [
                'date' => '2026-10-05',
                'time' => '18:00',
                'network' => 'instagram',
                'pillar' => 'Pilar B',
                'format' => 'image',
                'hook' => 'Hook B',
                'copy' => 'Copy B',
                'hashtags' => ['#B'],
                'design_payload' => ['slides' => [['title' => 'T', 'body' => 'B']]],
                'background_prompt' => 'a classroom',
            ],
        ]]);

        $this->app->instance(LlmClient::class, $fake);

        // La marca activa del contexto es Saberé (setUp); el job debe usar B.
        GenerateMonthlyPlan::dispatchSync($brandB, 2026, 10, '2026-10-05');

        $plan = MonthlyPlan::withoutGlobalScopes()
            ->where('brand_id', $brandB->id)->where('year', 2026)->where('month', 10)
            ->firstOrFail();

        $post = $plan->posts()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($brandB->id, $post->brand_id);
        $this->assertSame($pillar->id, $post->content_pillar_id);
        $this->assertSame('Hook B', $post->hook);

        // No se creó ningún plan para Saberé.
        $this->assertNull(
            MonthlyPlan::withoutGlobalScopes()
                ->where('brand_id', 1)->where('year', 2026)->where('month', 10)->first()
        );
    }

    /**
     * RenderPost fija la marca del post y escribe los assets bajo
     * brands/{brand_id}/posts/{id} de la marca del post.
     */
    public function test_render_post_writes_assets_under_its_own_brand(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->app->instance(\App\Services\Render\FrameRenderer::class, new \App\Services\Render\FakeFrameRenderer);

        $brandB = Brand::factory()->create();
        app()->instance('brand', $brandB);
        $post = \App\Models\Post::factory()->create([
            'brand_id' => $brandB->id,
            'network' => \App\Enums\Network::LinkedIn,
            'format' => \App\Enums\PostFormat::Image,
            'status' => \App\Enums\PostStatus::Approved,
        ]);

        // Contexto vuelve a Saberé; el job debe usar la marca del post.
        app()->instance('brand', Brand::firstOrFail());

        (new \App\Jobs\RenderPost($post))->handle(
            app(\App\Services\Render\FrameRenderer::class),
            app(\App\Services\Images\BackgroundService::class),
            app(\App\Services\Render\VideoAssembler::class),
        );

        \Illuminate\Support\Facades\Storage::disk('local')
            ->assertExists("brands/{$brandB->id}/posts/{$post->id}/frame-01.png");
        $this->assertSame(
            "brands/{$brandB->id}/posts/{$post->id}/frame-01.png",
            $post->fresh()->asset_path
        );
    }

    /**
     * RegeneratePost usa la marca del post para construir el prompt (perfil,
     * pilares y estrategias de SU marca).
     */
    public function test_regenerate_post_uses_its_own_brand_context(): void
    {
        $brandB = Brand::factory()->create();
        DefaultsSeeder::forBrand($brandB);
        ContentPillar::factory()->create([
            'brand_id' => $brandB->id,
            'name' => 'Pilar B',
            'weight' => 100,
        ]);

        app()->instance('brand', $brandB);
        $post = \App\Models\Post::factory()->create([
            'brand_id' => $brandB->id,
            'status' => \App\Enums\PostStatus::Drafted,
        ]);

        // Contexto vuelve a Saberé; el job debe usar la marca del post.
        app()->instance('brand', Brand::firstOrFail());

        $fake = (new FakeLlmClient)->withResponse([
            'hook' => 'Hook regenerado B',
            'copy' => 'Copy regenerado B',
            'hashtags' => ['#B'],
            'design_payload' => ['slides' => [['title' => 'T', 'body' => 'B']]],
            'background_prompt' => 'an office',
        ]);
        $this->app->instance(LlmClient::class, $fake);

        (new \App\Jobs\RegeneratePost($post))->handle(
            app(LlmClient::class),
            app(\App\Services\Planning\PromptBuilder::class),
        );

        $post->refresh();
        $this->assertSame('Hook regenerado B', $post->hook);
        $this->assertSame($brandB->id, $post->brand_id);
    }
}
