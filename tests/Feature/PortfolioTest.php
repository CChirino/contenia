<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\LlmLog;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_solo_admin(): void
    {
        $this->actingAsOperatorFor();
        $this->get('/portfolio')->assertForbidden();

        $this->actingAsAdminFor();
        $this->get('/portfolio')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Portfolio/Index'));
    }

    public function test_index_agrega_uso_y_costo_por_marca(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'AAA Demo',
            'plan_id' => Plan::factory()->create(['posts_per_month' => 10]),
        ]);
        $usage = $brand->usageFor(2026, 10);
        $usage->update(['posts_generated' => 4, 'image_calls' => 2]);

        // forceCreate: created_at no es fillable y debe quedar en el mes consultado.
        LlmLog::withoutGlobalScopes()->forceCreate([
            'brand_id' => $brand->id,
            'purpose' => 'test',
            'prompt' => 'p',
            'response' => 'r',
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'cost_usd' => 1.25,
            'model' => 'x',
            'duration_ms' => 1,
            'created_at' => '2026-10-10 10:00:00',
            'updated_at' => '2026-10-10 10:00:00',
        ]);

        $this->actingAsAdminFor();
        $this->get('/portfolio?month=2026-10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('period', '2026-10')
                ->where('rows.0.id', $brand->id)
                ->where('rows.0.actions.0.used', 4)
                ->where('rows.0.actions.0.limit', 10)
                ->where('rows.0.cost_usd', 1.25)
                ->where('rows.0.tokens', 1500)
                ->where('rows.0.image_calls', 2)
            );
    }

    public function test_extend_reemplaza_los_extras_del_mes(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAsAdminFor();
        $this->post(route('portfolio.extend', ['brand' => $brand->id]), [
            'month' => '2026-10',
            'extra_posts' => 5,
            'extra_regenerations' => 2,
            'extra_image_refreshes' => '',
        ])->assertRedirect();

        $usage = $brand->usageFor(2026, 10);
        $this->assertSame(5, (int) $usage->extra_posts);
        $this->assertSame(2, (int) $usage->extra_regenerations);
        $this->assertSame(0, (int) $usage->extra_image_refreshes);

        // Reemplaza (no suma): segunda extensión deja extra_posts en 1.
        $this->post(route('portfolio.extend', ['brand' => $brand->id]), [
            'month' => '2026-10',
            'extra_posts' => 1,
        ])->assertRedirect();

        $this->assertSame(1, (int) $brand->fresh()->usageFor(2026, 10)->extra_posts);
    }

    public function test_extend_solo_admin(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAsOperatorFor();
        $this->post(route('portfolio.extend', ['brand' => $brand->id]), [
            'month' => '2026-10', 'extra_posts' => 9,
        ])->assertForbidden();
    }
}
