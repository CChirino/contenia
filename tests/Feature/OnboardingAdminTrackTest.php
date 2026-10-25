<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\BrandProfile;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Recorrido A del wizard: marca, propuesta, aplicar todo, manual,
 * estado, preview y cierre a la grilla.
 */
class OnboardingAdminTrackTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        $this->user = User::factory()->create([
            'role' => UserRole::Admin,
            'onboarded_at' => null,
            'onboarding_state' => ['track' => 'admin'],
        ]);
        $this->actingAs($this->user);
    }

    private function plan(): Plan
    {
        return Plan::factory()->create(['networks' => ['instagram', 'linkedin']]);
    }

    public function test_store_brand_creates_brand_seeds_defaults_and_saves_state(): void
    {
        $plan = $this->plan();

        $this->post(route('onboarding.brand.store'), [
            'name' => 'Café Aurora',
            'website_url' => 'https://cafeaurora.com',
            'plan_id' => $plan->id,
            'requires_client_approval' => true,
        ])->assertRedirect();

        $brand = Brand::where('name', 'Café Aurora')->firstOrFail();
        $this->assertSame('https://cafeaurora.com', $brand->website_url);
        $this->assertTrue($brand->requires_client_approval);
        // DefaultsSeeder: perfil vacío creado.
        $this->assertNotNull(
            BrandProfile::withoutGlobalScopes()->where('brand_id', $brand->id)->first()
        );
        // Asignada al admin y guardada en el estado del wizard.
        $this->assertTrue($this->user->fresh()->brands()->whereKey($brand->id)->exists());
        $this->assertSame($brand->id, $this->user->fresh()->onboarding_state['brand_id']);
        $this->assertSame(3, $this->user->fresh()->onboarding_state['step']);
    }

    public function test_store_brand_updates_existing_state_brand(): void
    {
        $plan = $this->plan();
        $brand = Brand::factory()->create(['plan_id' => $plan->id]);
        $this->user->forceFill([
            'onboarding_state' => ['track' => 'admin', 'brand_id' => $brand->id],
        ])->save();

        $this->post(route('onboarding.brand.store'), [
            'name' => 'Nombre Nuevo',
            'website_url' => 'https://nueva.com',
            'plan_id' => $plan->id,
        ])->assertRedirect();

        $this->assertSame('Nombre Nuevo', $brand->fresh()->name);
        $this->assertSame(1, Brand::where('name', 'Nombre Nuevo')->count());
    }

    private function proposal(): array
    {
        return [
            'description' => 'Café de especialidad.',
            'tone_of_voice' => 'Cercano',
            'audience' => 'Jóvenes',
            'cta' => 'Pide tu café',
            'base_hashtags' => ['#cafe'],
            'pillars' => [
                ['name' => 'Orígenes', 'description' => 'Grano', 'weight' => 60],
                ['name' => 'Recetas', 'description' => 'Café', 'weight' => 40],
            ],
            'strategies' => [
                ['network' => 'instagram', 'posts_per_week' => 3, 'formats' => ['image'], 'focus' => 'Visual'],
                ['network' => 'linkedin', 'posts_per_week' => 2, 'formats' => ['image'], 'focus' => 'B2B'],
            ],
            'palette' => ['dark' => '#111111', 'light' => '#EEEEEE', 'accent' => '#AA3300', 'secondary' => '#224466'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function brandWithProposal(): Brand
    {
        $plan = $this->plan();
        $brand = Brand::factory()->create([
            'plan_id' => $plan->id,
            'onboarding_proposal' => $this->proposal(),
        ]);
        $this->user->forceFill([
            'onboarding_state' => ['track' => 'admin', 'brand_id' => $brand->id],
        ])->save();

        return $brand;
    }

    public function test_apply_all_applies_the_four_sections(): void
    {
        $brand = $this->brandWithProposal();

        $this->post(route('onboarding.apply-all'))->assertRedirect();

        $brand->refresh();
        $applied = $brand->onboarding_proposal['applied'] ?? [];
        foreach (['profile', 'pillars', 'strategies', 'palette'] as $section) {
            $this->assertTrue($applied[$section] ?? false, "falta {$section}");
        }
        $this->assertNotNull($brand->onboarded_at);
        $this->assertSame(2, $brand->pillars()->count());
        $this->assertSame('#AA3300', $brand->palette['accent']);
        $this->assertSame(
            'Café de especialidad.',
            BrandProfile::withoutGlobalScopes()->where('brand_id', $brand->id)->first()->description
        );
    }

    public function test_manual_setup_writes_profile_and_generic_pillars(): void
    {
        $plan = $this->plan();
        $brand = Brand::factory()->create([
            'plan_id' => $plan->id,
            'onboarding_proposal' => $this->proposal(),
        ]);
        // Sin pilares: los crea manualSetup.
        $brand->pillars()->delete();
        $this->user->forceFill([
            'onboarding_state' => ['track' => 'admin', 'brand_id' => $brand->id],
        ])->save();

        $this->post(route('onboarding.manual'), [
            'description' => 'Taller de bicicletas.',
            'tone_of_voice' => 'Directo',
            'audience' => 'Ciclistas urbanos',
        ])->assertRedirect();

        $brand->refresh();
        $pillars = $brand->pillars()->get();
        $this->assertCount(3, $pillars);
        $this->assertSame(100, $pillars->sum('weight'));
        $this->assertSame('Educativo', $pillars->first()->name);
        $this->assertTrue($brand->onboarding_proposal['applied']['profile'] ?? false);
        $this->assertTrue($brand->onboarding_proposal['applied']['pillars'] ?? false);
    }

    public function test_manual_setup_does_not_touch_existing_pillars(): void
    {
        $plan = $this->plan();
        $brand = Brand::factory()->create(['plan_id' => $plan->id]);
        $brand->pillars()->create(['name' => 'Existente', 'description' => '', 'weight' => 100]);
        $this->user->forceFill([
            'onboarding_state' => ['track' => 'admin', 'brand_id' => $brand->id],
        ])->save();

        $this->post(route('onboarding.manual'), [])->assertRedirect();

        $this->assertSame(1, $brand->pillars()->count());
        $this->assertSame('Existente', $brand->pillars()->first()->name);
    }

    public function test_status_returns_proposal_pending_and_plan(): void
    {
        $brand = $this->brandWithProposal();
        Cache::put("onboarding:{$brand->id}", true, 60);

        $this->getJson(route('onboarding.status').'?month=2026-11')
            ->assertOk()
            ->assertJsonPath('pending', true)
            ->assertJsonPath('pillars_count', 0)
            ->assertJsonStructure([
                'pending', 'proposal', 'applied',
                'pillars_count', 'contacts_count', 'requires_client_approval',
                'plan' => ['generating_at', 'posts_count', 'last_error'],
            ]);
    }

    public function test_complete_marks_onboarded_and_redirects_to_grid(): void
    {
        $brand = Brand::factory()->create();
        $this->user->forceFill([
            'onboarding_state' => ['track' => 'admin', 'brand_id' => $brand->id, 'month' => '2027-03'],
        ])->save();

        $this->post(route('onboarding.complete'))
            ->assertRedirect(route('plans.show', [
                'brand' => $brand->slug, 'year' => 2027, 'month' => 3,
            ]));

        $this->assertNotNull($this->user->fresh()->onboarded_at);
    }

    public function test_preview_renders_frame_with_brand_palette(): void
    {
        $brand = Brand::factory()->create([
            'palette' => ['dark' => '#111111', 'light' => '#EEEEEE', 'accent' => '#AA3300', 'secondary' => '#224466'],
        ]);
        DefaultsSeeder::forBrand($brand);
        $this->user->forceFill([
            'onboarding_state' => ['track' => 'admin', 'brand_id' => $brand->id],
        ])->save();

        $this->get(route('onboarding.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Render/Frame')
                ->where('palette.accent', '#AA3300')
                ->where('wordmark', $brand->name.'.')
            );
    }

    public function test_show_renders_admin_track_when_state_says_so(): void
    {
        $this->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Index')
                ->where('track', 'admin')
            );
    }
}
