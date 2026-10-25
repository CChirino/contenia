<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureOnboardedTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_onboarded_at_is_redirected_to_wizard(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'onboarded_at' => null]);
        $user->brands()->sync([1]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('onboarding.show'));

        $this->actingAs($user)
            ->get('/b/sabere/settings/brand')
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_onboarded_user_passes_through(): void
    {
        $this->actingAsAdminFor();

        $response = $this->get('/dashboard');
        $response->assertRedirect(); // va a la grilla, no al wizard
        $this->assertNotSame(route('onboarding.show'), $response->headers->get('Location'));
    }

    public function test_exempt_routes_do_not_redirect(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'onboarded_at' => null]);

        // El propio wizard, el perfil y el logout no se ven afectados.
        $this->actingAs($user)->get('/onboarding')->assertOk();
        $this->actingAs($user)->get('/profile')->assertOk();
        $this->actingAs($user)->post('/logout')->assertRedirect();
    }

    public function test_skip_marks_user_onboarded(): void
    {
        $user = User::factory()->create(['onboarded_at' => null]);

        $this->actingAs($user)->post(route('onboarding.skip'))->assertRedirect();

        $this->assertNotNull($user->fresh()->onboarded_at);
    }

    public function test_restart_clears_onboarded_but_keeps_state(): void
    {
        $user = User::factory()->create([
            'onboarded_at' => now(),
            'onboarding_state' => ['track' => 'operator', 'step' => 3],
        ]);

        $this->actingAs($user)->post(route('onboarding.restart'))
            ->assertRedirect(route('onboarding.show'));

        $fresh = $user->fresh();
        $this->assertNull($fresh->onboarded_at);
        $this->assertSame('operator', $fresh->onboarding_state['track']);
    }

    public function test_state_persists_track_step_and_month(): void
    {
        $user = User::factory()->create(['onboarded_at' => null]);

        $this->actingAs($user)->patch(route('onboarding.state'), [
            'track' => 'admin',
            'step' => 3,
            'month' => '2026-11',
        ]);

        $state = $user->fresh()->onboarding_state;
        $this->assertSame('admin', $state['track']);
        $this->assertSame(3, $state['step']);
        $this->assertSame('2026-11', $state['month']);
    }

    public function test_state_rejects_brand_without_access(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Operator,
            'onboarded_at' => null,
        ]);
        // Operador sin marcas asignadas: no puede fijar brand_id.
        $this->actingAs($user)->patch(route('onboarding.state'), [
            'brand_id' => 1,
        ])->assertForbidden();
    }

    public function test_factory_default_is_not_onboarded_and_seeder_is(): void
    {
        $this->assertNull(User::factory()->create()->onboarded_at);

        $this->seed(AdminUserSeeder::class);
        $this->assertNotNull(
            User::where('email', 'admin@sabere.com')->first()->onboarded_at
        );
    }
}
