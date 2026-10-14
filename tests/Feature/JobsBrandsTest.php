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
}
