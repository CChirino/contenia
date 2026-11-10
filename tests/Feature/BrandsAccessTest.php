<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Rol gestor: crea marcas y administra solo las suyas (asignadas vía
 * brand_user), vincula/desvincula operadores. Sin acceso a usuarios,
 * catálogo de planes ni cartera.
 */
class BrandsAccessTest extends TestCase
{
    use RefreshDatabase;

    private function brandPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Marca Gestor',
            'website_url' => 'https://ejemplo.com',
            'plan_id' => Plan::firstOrFail()->id,
            'palette' => [
                'dark' => '#111111',
                'light' => '#EEEEEE',
                'accent' => '#FFAA00',
                'secondary' => '#00AAFF',
            ],
            'requires_client_approval' => false,
        ], $overrides);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::Manager]);
    }

    public function test_manager_sees_only_assigned_brands(): void
    {
        $assigned = Brand::factory()->create();
        Brand::factory()->create(); // marca ajena
        $sabere = Brand::where('slug', 'sabere')->firstOrFail();

        $manager = $this->manager();
        $manager->brands()->sync([$assigned->id]);

        $this->actingAs($manager)
            ->get('/brands')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Brands/Index')
                ->has('brands', 1)
                ->where('brands.0.slug', $assigned->slug)
            );

        $this->assertNotSame($sabere->id, $assigned->id);
    }

    public function test_admin_still_sees_all_brands(): void
    {
        Brand::factory()->count(2)->create();
        $this->actingAsAdminFor();

        $this->get('/brands')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('brands', Brand::count())
            );
    }

    public function test_manager_creates_brand_and_is_auto_assigned(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);

        $this->post('/brands', $this->brandPayload())->assertRedirect('/brands');

        $brand = Brand::where('name', 'Marca Gestor')->firstOrFail();
        $this->assertTrue($manager->fresh()->brands()->whereKey($brand->id)->exists());
    }

    public function test_manager_edits_own_brand_and_receives_operator_options(): void
    {
        $brand = Brand::factory()->create();
        $this->actingAsManagerFor($brand);
        User::factory()->create(['role' => UserRole::Operator]);

        $this->get("/brands/{$brand->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Brands/Form')
                ->has('operators', 1)
                ->where('operators.0.assigned', false)
            );

        $this->put("/brands/{$brand->id}", $this->brandPayload(['name' => 'Renombrada']))
            ->assertRedirect('/brands');
        $this->assertSame('Renombrada', $brand->fresh()->name);

        $this->post("/brands/{$brand->id}/toggle")->assertRedirect('/brands');
    }

    public function test_manager_cannot_reach_users_plans_or_portfolio(): void
    {
        $this->actingAsManagerFor();

        $this->get('/users')->assertForbidden();
        $this->get('/plans')->assertForbidden();
        $this->get('/portfolio')->assertForbidden();
    }

    public function test_manager_cannot_manage_foreign_brand(): void
    {
        $foreign = Brand::factory()->create();
        $this->actingAsManagerFor(); // asignado solo a Saberé

        $this->get("/brands/{$foreign->id}/edit")->assertForbidden();
        $this->put("/brands/{$foreign->id}", $this->brandPayload())->assertForbidden();
        $this->post("/brands/{$foreign->id}/toggle")->assertForbidden();
        $this->post("/brands/{$foreign->id}/contacts", [
            'name' => 'Ana',
            'email' => 'ana@ejemplo.com',
        ])->assertForbidden();
        $this->post("/brands/{$foreign->id}/users", [
            'user_ids' => [],
        ])->assertForbidden();
    }

    public function test_operator_cannot_manage_brands(): void
    {
        $brand = Brand::firstOrFail();
        $this->actingAsOperatorFor($brand);

        $this->get('/brands')->assertForbidden();
        $this->post('/brands', $this->brandPayload())->assertForbidden();
        $this->get("/brands/{$brand->id}/edit")->assertForbidden();
    }

    public function test_brand_users_sync_assigns_only_operators_of_own_brand(): void
    {
        $brand = Brand::factory()->create();
        $manager = $this->actingAsManagerFor($brand);

        $op1 = User::factory()->create(['role' => UserRole::Operator]);
        $op2 = User::factory()->create(['role' => UserRole::Operator]);
        $otherManager = $this->manager();

        $this->post("/brands/{$brand->id}/users", [
            'user_ids' => [$op1->id, $op2->id],
        ])->assertRedirect();

        $this->assertTrue($op1->fresh()->brands->contains($brand));
        $this->assertTrue($op2->fresh()->brands->contains($brand));
        // El gestor conserva su asignación (no es operador).
        $this->assertTrue($manager->fresh()->brands->contains($brand));

        // Solo acepta operadores: un gestor da 422.
        $this->post("/brands/{$brand->id}/users", [
            'user_ids' => [$otherManager->id],
        ])->assertStatus(422);

        // Sync vacío desvincula operadores y conserva al gestor.
        $this->post("/brands/{$brand->id}/users", ['user_ids' => []])->assertRedirect();
        $this->assertFalse($op1->fresh()->brands->contains($brand));
        $this->assertFalse($op2->fresh()->brands->contains($brand));
        $this->assertTrue($manager->fresh()->brands->contains($brand));
    }

    public function test_brand_users_attach_and_detach_single_operator(): void
    {
        $brand = Brand::factory()->create();
        $this->actingAsManagerFor($brand);

        $op = User::factory()->create(['role' => UserRole::Operator]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->post("/brands/{$brand->id}/users/{$op->id}")->assertRedirect();
        $this->assertTrue($op->fresh()->brands->contains($brand));

        $this->post("/brands/{$brand->id}/users/{$admin->id}")->assertStatus(422);
        $this->assertFalse($admin->fresh()->brands->contains($brand));

        $this->delete("/brands/{$brand->id}/users/{$op->id}")->assertRedirect();
        $this->assertFalse($op->fresh()->brands->contains($brand));
    }

    public function test_operator_without_brands_gets_nobrands_page_on_complete(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Operator,
            'onboarded_at' => null,
            'onboarding_state' => ['track' => 'operator', 'step' => 5],
        ]);

        $this->actingAs($user)->post(route('onboarding.complete'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/NoBrands')
            );
    }

    public function test_manager_without_brands_gets_creation_track(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Manager,
            'onboarded_at' => null,
        ]);

        $this->actingAs($user)->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Index')
                ->where('track', 'admin')
            );
    }

    public function test_manager_with_brands_gets_choose_page(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Manager,
            'onboarded_at' => null,
        ]);
        $user->brands()->sync([Brand::where('slug', 'sabere')->firstOrFail()->id]);

        $this->actingAs($user)->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Choose')
            );
    }

    public function test_manager_store_brand_via_onboarding_auto_assigns(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Manager,
            'onboarded_at' => null,
            'onboarding_state' => ['track' => 'admin'],
        ]);

        $this->actingAs($user)->post(route('onboarding.brand.store'), [
            'name' => 'Marca del Gestor',
            'plan_id' => Plan::firstOrFail()->id,
        ])->assertRedirect();

        $brand = Brand::where('name', 'Marca del Gestor')->firstOrFail();
        $this->assertTrue($user->fresh()->brands()->whereKey($brand->id)->exists());
    }

    public function test_gestor_sin_marcas_redirige_al_wizard_desde_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        $this->get('/dashboard')->assertRedirect(route('onboarding.show'));
    }

    public function test_operador_sin_marcas_ve_pagina_amable_en_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]));

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Onboarding/NoBrands'));
    }

    public function test_gestor_con_track_operador_antiguo_sale_del_bucle_al_completar(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Manager,
            'onboarding_state' => ['track' => 'operator', 'step' => 5],
        ]);

        // Sin marca y con track 'operator' obsoleto, completar el
        // recorrido cambiaba a show con el mismo track → bucle infinito.
        $this->actingAs($user)
            ->post(route('onboarding.complete'))
            ->assertRedirect(route('onboarding.show'));

        $this->assertSame('admin', $user->fresh()->onboarding_state['track']);
    }
}
