<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Recorrido B del wizard: el operador ve la guía de su marca, no puede
 * crear marcas y termina en su grilla.
 */
class OnboardingOperatorTrackTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_gets_operator_track_and_his_brands(): void
    {
        $brand = Brand::where('slug', 'sabere')->first();
        $user = User::factory()->create([
            'role' => UserRole::Operator,
            'onboarded_at' => null,
        ]);
        $user->brands()->sync([$brand->id]);

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Index')
                ->where('track', 'operator')
                ->has('brands', 1)
            );
    }

    public function test_operator_cannot_store_brand(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Operator,
            'onboarded_at' => null,
        ]);
        $user->brands()->sync([1]);

        $this->actingAs($user)->post(route('onboarding.brand.store'), [
            'name' => 'X',
            'plan_id' => 1,
        ])->assertForbidden();
    }

    public function test_complete_sends_operator_to_his_grid(): void
    {
        $brand = Brand::where('slug', 'sabere')->first();
        $user = User::factory()->create([
            'role' => UserRole::Operator,
            'onboarded_at' => null,
            'onboarding_state' => ['track' => 'operator', 'step' => 5, 'month' => '2026-12'],
        ]);
        $user->brands()->sync([$brand->id]);

        $this->actingAs($user)->post(route('onboarding.complete'))
            ->assertRedirect(route('plans.show', [
                'brand' => 'sabere', 'year' => 2026, 'month' => 12,
            ]));

        $this->assertNotNull($user->fresh()->onboarded_at);
    }
}
