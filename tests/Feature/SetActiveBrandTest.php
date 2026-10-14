<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetActiveBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_not_assigned_gets_403(): void
    {
        $brandB = Brand::factory()->create();
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        // Asignado solo a Saberé, no a B.
        $operator->brands()->attach(Brand::first());

        $this->actingAs($operator)
            ->get("/b/{$brandB->slug}/plans/2026/10")
            ->assertForbidden();
    }

    public function test_unknown_slug_is_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/b/no-existe/plans/2026/10')
            ->assertNotFound();
    }

    public function test_admin_views_any_brand(): void
    {
        $brandB = Brand::factory()->create();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get("/b/{$brandB->slug}/plans/2026/10")
            ->assertOk();
    }

    public function test_last_brand_id_is_updated(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->get('/b/sabere/plans/2026/10');

        $this->assertSame(1, $user->fresh()->last_brand_id);
    }

    public function test_legacy_plans_url_redirects_301(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/plans/2026/10')
            ->assertRedirect('/b/sabere/plans/2026/10');
    }
}
