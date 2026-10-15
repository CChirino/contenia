<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\NetworkStrategy;
use App\Models\Plan;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkStrategyPlanTest extends TestCase
{
    use RefreshDatabase;

    private function brandLinkedinOnly(): Brand
    {
        $plan = Plan::factory()->create(['networks' => ['linkedin']]);
        $brand = Brand::factory()->create(['plan_id' => $plan->id]);
        DefaultsSeeder::forBrand($brand);

        return $brand;
    }

    public function test_index_expone_redes_permitidas_y_nombre_del_plan(): void
    {
        $brand = $this->brandLinkedinOnly();
        $brand->plan->update(['name' => 'Básico X']);

        $this->actingAsAdminFor($brand);
        $this->get("/b/{$brand->slug}/settings/networks")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('allowed_networks', ['linkedin'])
                ->where('plan_name', 'Básico X')
            );
    }

    public function test_update_fuerza_cero_en_redes_fuera_del_plan(): void
    {
        $brand = $this->brandLinkedinOnly();
        $ig = NetworkStrategy::where('brand_id', $brand->id)->where('network', 'instagram')->firstOrFail();
        $li = NetworkStrategy::where('brand_id', $brand->id)->where('network', 'linkedin')->firstOrFail();

        $this->actingAsAdminFor($brand);
        $this->put("/b/{$brand->slug}/settings/networks", [
            'strategies' => [
                [
                    'id' => $ig->id,
                    'posts_per_week' => 6, // intento de cadencia en red no permitida
                    'preferred_formats' => ['image'],
                    'focus' => 'f1',
                ],
                [
                    'id' => $li->id,
                    'posts_per_week' => 3,
                    'preferred_formats' => ['image'],
                    'focus' => 'f2',
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(0, (int) $ig->fresh()->posts_per_week);
        $this->assertSame(3, (int) $li->fresh()->posts_per_week);
    }
}
