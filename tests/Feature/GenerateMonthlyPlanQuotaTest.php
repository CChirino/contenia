<?php

namespace Tests\Feature;

use App\Enums\QuotaAction;
use App\Jobs\GenerateMonthlyPlan;
use App\Models\Brand;
use App\Models\BrandUsage;
use App\Models\ContentPillar;
use App\Models\LlmLog;
use App\Models\MonthlyPlan;
use App\Models\NetworkStrategy;
use App\Models\Plan;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Quota\QuotaGuard;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateMonthlyPlanQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function brandConPlan(array $planAttrs, array $freqs): Brand
    {
        $plan = Plan::factory()->create(array_merge([
            'name' => 'Básico test',
            'posts_per_month' => 5,
            'regenerations_per_month' => null,
            'image_refreshes_per_month' => null,
            'networks' => ['instagram', 'linkedin', 'tiktok'],
        ], $planAttrs));

        $brand = Brand::factory()->create(['plan_id' => $plan->id]);
        DefaultsSeeder::forBrand($brand);
        ContentPillar::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Pilar',
            'weight' => 100,
        ]);

        foreach ($freqs as $network => $freq) {
            NetworkStrategy::where('brand_id', $brand->id)
                ->where('network', $network)
                ->update(['posts_per_week' => $freq]);
        }

        return $brand;
    }

    private function postFixture(string $date, string $network = 'instagram', string $format = 'image'): array
    {
        return [
            'date' => $date,
            'time' => '18:00',
            'network' => $network,
            'pillar' => 'Pilar',
            'format' => $format,
            'hook' => "Hook {$date} {$network}",
            'copy' => 'Copy',
            'hashtags' => ['#x'],
            'design_payload' => ['slides' => [['title' => 'T', 'body' => 'B']]],
            'background_prompt' => 'un aula',
        ];
    }

    public function test_el_cupo_recorta_la_generacion_y_lo_anota(): void
    {
        // Plan de 5 posts/mes; la estrategia pide mucho más por semana.
        $brand = $this->brandConPlan(['posts_per_month' => 5], ['instagram' => 10, 'linkedin' => 0, 'tiktok' => 0]);

        $fake = new FakeLlmClient;
        // Semana 1: el LLM devuelve 6 → se quedan 5. Semanas siguientes se saltan (cupo 0).
        foreach (['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06'] as $d) {
            $fake->withResponse(['posts' => [
                $this->postFixture($d), $this->postFixture($d, 'instagram'),
            ]]);
        }
        $this->app->instance(LlmClient::class, $fake);

        GenerateMonthlyPlan::dispatchSync($brand, 2026, 10);

        $plan = MonthlyPlan::withoutGlobalScopes()
            ->where('brand_id', $brand->id)->where('year', 2026)->where('month', 10)
            ->firstOrFail();

        $this->assertLessThanOrEqual(5, $plan->posts()->count());
        $this->assertSame(5, (int) $brand->usageFor(2026, 10)->posts_generated);
        $this->assertNotNull($plan->quota_notice);
        $this->assertStringContainsString('cupo', $plan->quota_notice);
    }

    public function test_el_prompt_recibe_el_tope_proporcional_por_red(): void
    {
        // Dos redes a 7/semana piden 14; cupo 4 → reparto 2+2.
        $brand = $this->brandConPlan(['posts_per_month' => 4], ['instagram' => 7, 'linkedin' => 7, 'tiktok' => 0]);

        $fake = (new FakeLlmClient)->withResponse(['posts' => []]);
        $this->app->instance(LlmClient::class, $fake);

        GenerateMonthlyPlan::dispatchSync($brand, 2026, 10, '2026-10-05');

        $prompt = LlmLog::withoutGlobalScopes()->latest('id')->firstOrFail()->prompt;
        $this->assertStringContainsString('instagram: 2 posts para esta semana', $prompt);
        $this->assertStringContainsString('linkedin: 2 posts para esta semana', $prompt);
        $this->assertStringContainsString('tiktok: 0 posts para esta semana', $prompt);
    }

    public function test_las_semanas_posteriores_se_saltan_con_cupo_agotado(): void
    {
        $brand = $this->brandConPlan(['posts_per_month' => 2], ['instagram' => 4, 'linkedin' => 0, 'tiktok' => 0]);

        $fake = (new FakeLlmClient)->withResponse(['posts' => [
            $this->postFixture('2026-10-05'),
            $this->postFixture('2026-10-06'),
        ]]);
        $this->app->instance(LlmClient::class, $fake);

        GenerateMonthlyPlan::dispatchSync($brand, 2026, 10);

        // Una sola llamada LLM (la primera semana); las demás se saltaron.
        $this->assertSame(1, LlmLog::withoutGlobalScopes()->where('purpose', 'monthly_plan')->count());
        $this->assertSame(2, (int) $brand->usageFor(2026, 10)->posts_generated);

        $plan = MonthlyPlan::withoutGlobalScopes()->where('brand_id', $brand->id)->firstOrFail();
        $this->assertStringContainsString('cupo de posts agotado', $plan->quota_notice);
    }

    public function test_plan_ilimitado_no_recorta_ni_anota(): void
    {
        $brand = $this->brandConPlan(
            ['posts_per_month' => null],
            ['instagram' => 3, 'linkedin' => 0, 'tiktok' => 0]
        );

        $fake = (new FakeLlmClient)->withResponse(['posts' => [
            $this->postFixture('2026-10-05'),
            $this->postFixture('2026-10-06'),
            $this->postFixture('2026-10-07'),
        ]]);
        $this->app->instance(LlmClient::class, $fake);

        GenerateMonthlyPlan::dispatchSync($brand, 2026, 10, '2026-10-05');

        $plan = MonthlyPlan::withoutGlobalScopes()
            ->where('brand_id', $brand->id)->where('year', 2026)->where('month', 10)
            ->firstOrFail();

        $this->assertSame(3, $plan->posts()->count());
        $this->assertNull($plan->quota_notice);
        // El uso se registra aunque el plan sea ilimitado.
        $this->assertSame(3, (int) $brand->usageFor(2026, 10)->posts_generated);
    }

    public function test_red_no_incluida_en_el_plan_no_entra_al_prompt_ni_persiste(): void
    {
        $brand = $this->brandConPlan(
            ['networks' => ['linkedin']],
            ['instagram' => 4, 'linkedin' => 4, 'tiktok' => 4]
        );

        $fake = (new FakeLlmClient)->withResponse(['posts' => [
            $this->postFixture('2026-10-05', 'instagram'),   // red no permitida → descartado
            $this->postFixture('2026-10-06', 'linkedin'),
        ]]);
        $this->app->instance(LlmClient::class, $fake);

        GenerateMonthlyPlan::dispatchSync($brand, 2026, 10, '2026-10-05');

        $prompt = LlmLog::withoutGlobalScopes()->latest('id')->firstOrFail()->prompt;
        $this->assertStringContainsString('linkedin:', $prompt);
        $this->assertStringNotContainsString('instagram:', $prompt);
        $this->assertStringNotContainsString('tiktok:', $prompt);

        $plan = MonthlyPlan::withoutGlobalScopes()->where('brand_id', $brand->id)->firstOrFail();
        $this->assertSame(1, $plan->posts()->count());
        $this->assertSame('linkedin', $plan->posts()->firstOrFail()->network->value);
    }

    public function test_el_controlador_devuelve_422_con_cupo_agotado(): void
    {
        $brand = $this->brandConPlan(['posts_per_month' => 2], ['instagram' => 4, 'linkedin' => 0, 'tiktok' => 0]);
        $brand->usageFor(2026, 10)->update(['posts_generated' => 2]);

        $this->actingAsAdminFor($brand);
        $this->post(route('plans.generate', ['brand' => $brand->slug, 'year' => 2026, 'month' => 10]))
            ->assertSessionHasErrors('quota');
    }

    public function test_los_extras_permiten_seguir_generando(): void
    {
        $brand = $this->brandConPlan(['posts_per_month' => 2], ['instagram' => 4, 'linkedin' => 0, 'tiktok' => 0]);
        $brand->usageFor(2026, 10)->update(['posts_generated' => 2, 'extra_posts' => 1]);

        $quota = app(QuotaGuard::class);
        $this->assertSame(1, $quota->remaining($brand, QuotaAction::PostsGenerated, 2026, 10));

        $fake = (new FakeLlmClient)->withResponse(['posts' => [
            $this->postFixture('2026-10-05'),
            $this->postFixture('2026-10-06'),
        ]]);
        $this->app->instance(LlmClient::class, $fake);

        GenerateMonthlyPlan::dispatchSync($brand, 2026, 10, '2026-10-05');

        // Solo 1 pasa: el extra.
        $this->assertSame(3, (int) $brand->fresh()->usageFor(2026, 10)->posts_generated);
        $plan = MonthlyPlan::withoutGlobalScopes()->where('brand_id', $brand->id)->firstOrFail();
        $this->assertSame(1, $plan->posts()->count());
    }

    public function test_la_cuota_usa_el_mes_del_contenido_no_el_actual(): void
    {
        // Generar la grilla de un mes distinto al actual registra en ese mes.
        $brand = $this->brandConPlan(['posts_per_month' => 5], ['instagram' => 4, 'linkedin' => 0, 'tiktok' => 0]);

        $fake = (new FakeLlmClient)->withResponse(['posts' => [
            $this->postFixture('2026-11-02'),
        ]]);
        $this->app->instance(LlmClient::class, $fake);

        GenerateMonthlyPlan::dispatchSync($brand, 2026, 11, '2026-11-02');

        $this->assertSame(1, (int) $brand->usageFor(2026, 11)->posts_generated);
        $this->assertNull(
            BrandUsage::withoutGlobalScopes()
                ->where('brand_id', $brand->id)->where('year', 2026)->where('month', 10)->first()
        );
    }
}
