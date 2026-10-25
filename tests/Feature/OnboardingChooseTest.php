<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin con marcas existentes y onboarded_at = null: primero elige
 * entre "marca nueva con IA" (A) y "ver cómo funciona" (B).
 */
class OnboardingChooseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_brands_and_no_track_gets_choose_page(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'onboarded_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Onboarding/Choose'));
    }

    public function test_choosing_track_persists_and_shows_index(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'onboarded_at' => null,
        ]);

        $this->actingAs($user)->patch(route('onboarding.state'), [
            'track' => 'operator',
        ]);

        $this->assertSame('operator', $user->fresh()->onboarding_state['track']);

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Index')
                ->where('track', 'operator')
            );
    }

    public function test_admin_tour_receives_all_brands_with_last_used_first(): void
    {
        $other = Brand::factory()->create(['name' => 'Aaa Marca']);
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'onboarded_at' => null,
            'last_brand_id' => $other->id,
            'onboarding_state' => ['track' => 'operator', 'step' => 1],
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Index')
                ->has('brands', Brand::count())
                ->where('brands.0.slug', $other->slug)
            );
    }
}
