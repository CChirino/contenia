<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_solo_admin(): void
    {
        $this->actingAsOperatorFor();
        $this->get('/plans')->assertForbidden();

        $this->actingAsAdminFor();
        $this->get('/plans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Plans/Index')->has('plans', 3));
    }

    public function test_store_crea_plan(): void
    {
        $this->actingAsAdminFor();

        $this->post('/plans', [
            'name' => 'Emprendedor',
            'posts_per_month' => 20,
            'regenerations_per_month' => '',
            'image_refreshes_per_month' => 5,
            'networks' => ['instagram', 'linkedin'],
            'price_label' => '$29/mes',
        ])->assertRedirect(route('admin.plans.index'));

        $plan = Plan::where('name', 'Emprendedor')->firstOrFail();
        $this->assertSame(20, (int) $plan->posts_per_month);
        $this->assertNull($plan->regenerations_per_month); // vacío = ilimitado
        $this->assertSame(5, (int) $plan->image_refreshes_per_month);
        $this->assertSame(['instagram', 'linkedin'], $plan->networks);
        $this->assertSame('$29/mes', $plan->price_label);
    }

    public function test_update_modifica_plan(): void
    {
        $plan = Plan::factory()->create(['name' => 'Viejo', 'posts_per_month' => 10]);

        $this->actingAsAdminFor();

        $this->put("/plans/{$plan->id}", [
            'name' => 'Nuevo',
            'posts_per_month' => '',
            'regenerations_per_month' => 4,
            'image_refreshes_per_month' => 4,
            'networks' => ['tiktok'],
            'price_label' => null,
        ])->assertRedirect(route('admin.plans.index'));

        $plan->refresh();
        $this->assertSame('Nuevo', $plan->name);
        $this->assertNull($plan->posts_per_month);
        $this->assertSame(['tiktok'], $plan->networks);
    }

    public function test_destroy_rechaza_plan_con_marcas(): void
    {
        $brand = Brand::factory()->create();
        $plan = $brand->plan;

        $this->actingAsAdminFor();
        $this->delete("/plans/{$plan->id}")
            ->assertSessionHasErrors('plan');

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_destroy_elimina_plan_sin_marcas(): void
    {
        $plan = Plan::factory()->create();

        $this->actingAsAdminFor();
        $this->delete("/plans/{$plan->id}")->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_rutas_admin_no_llevan_parametro_de_marca(): void
    {
        // admin.* está excluido de la inyección de {brand} en useBrandRoute.
        $this->actingAsAdminFor();
        $this->get(route('admin.plans.index'))->assertOk();

        $this->assertSame('/plans', parse_url(route('admin.plans.index'), PHP_URL_PATH));
    }
}
