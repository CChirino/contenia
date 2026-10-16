<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandProfile;
use App\Models\ContentPillar;
use App\Models\NetworkStrategy;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aplicar la propuesta de onboarding por secciones (spec §8.1):
 * cada endpoint escribe su sección en las tablas reales y marca
 * onboarding_proposal.applied.{sección}; con las cuatro se fija
 * onboarded_at. Solo admin.
 */
class ApplyProposalTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        // Plan de dos redes para probar que tiktok se ignora.
        $plan = Plan::factory()->create(['networks' => ['instagram', 'linkedin']]);
        $this->brand = Brand::factory()->create([
            'plan_id' => $plan->id,
            'onboarding_proposal' => $this->proposal(),
        ]);
    }

    private function proposal(): array
    {
        return [
            'description' => 'Café de especialidad de origen venezolano.',
            'tone_of_voice' => 'Cercano y experto',
            'audience' => 'Jóvenes profesionales',
            'cta' => 'Pide tu café',
            'base_hashtags' => ['#cafe', '#especialidad'],
            'pillars' => [
                ['name' => 'Orígenes', 'description' => 'Historia del grano', 'weight' => 60],
                ['name' => 'Recetas', 'description' => 'Preparaciones', 'weight' => 40],
            ],
            'strategies' => [
                ['network' => 'instagram', 'posts_per_week' => 3, 'formats' => ['image', 'reel'], 'focus' => 'Visual'],
                ['network' => 'linkedin', 'posts_per_week' => 2, 'formats' => ['image'], 'focus' => 'B2B'],
            ],
            'palette' => [
                'dark' => '#111111',
                'light' => '#EEEEEE',
                'accent' => '#AA3300',
                'secondary' => '#224466',
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function test_apply_profile_writes_brand_profile_and_marks_applied(): void
    {
        // Admin con otra marca activa: el endpoint debe escribir en la
        // marca del route-binding, no en la activa.
        $this->actingAsAdminFor(Brand::where('slug', 'sabere')->first());

        $this->post(route('brands.onboarding.apply.profile', $this->brand), [
            'description' => 'Café de especialidad de origen venezolano.',
            'tone_of_voice' => 'Cercano y experto',
            'audience' => 'Jóvenes profesionales',
            'cta' => 'Pide tu café',
            'base_hashtags' => ['cafe', '#especialidad'],
        ])->assertRedirect();

        $profile = BrandProfile::withoutGlobalScopes()
            ->where('brand_id', $this->brand->id)
            ->first();

        $this->assertNotNull($profile);
        $this->assertSame('Café de especialidad de origen venezolano.', $profile->description);
        $this->assertSame('Cercano y experto', $profile->tone_of_voice);
        $this->assertSame(['#cafe', '#especialidad'], $profile->base_hashtags);

        $this->brand->refresh();
        $this->assertTrue($this->brand->onboarding_proposal['applied']['profile'] ?? false);
        $this->assertNull($this->brand->onboarded_at);
    }

    public function test_apply_pillars_creates_pillars_and_marks_applied(): void
    {
        $this->actingAsAdminFor($this->brand);

        $this->post(route('brands.onboarding.apply.pillars', $this->brand), [
            'pillars' => [
                ['name' => 'Orígenes', 'description' => 'Historia', 'weight' => 60],
                ['name' => 'Recetas', 'description' => 'Preparaciones', 'weight' => 40],
            ],
        ])->assertRedirect();

        $pillars = ContentPillar::withoutGlobalScopes()
            ->where('brand_id', $this->brand->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $pillars);
        $this->assertSame(100, $pillars->sum('weight'));
        $this->assertTrue($this->brand->fresh()->onboarding_proposal['applied']['pillars'] ?? false);
    }

    public function test_apply_pillars_rejects_weight_sum_other_than_100(): void
    {
        $this->actingAsAdminFor($this->brand);

        $this->postJson(route('brands.onboarding.apply.pillars', $this->brand), [
            'pillars' => [
                ['name' => 'A', 'description' => '', 'weight' => 60],
                ['name' => 'B', 'description' => '', 'weight' => 30],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, ContentPillar::withoutGlobalScopes()
            ->where('brand_id', $this->brand->id)->count());
    }

    public function test_apply_pillars_requires_replace_when_brand_has_pillars(): void
    {
        $this->actingAsAdminFor($this->brand);
        ContentPillar::factory()->create(['brand_id' => $this->brand->id, 'name' => 'Viejo']);

        $payload = [
            'pillars' => [
                ['name' => 'Nuevo A', 'description' => '', 'weight' => 50],
                ['name' => 'Nuevo B', 'description' => '', 'weight' => 50],
            ],
        ];

        // Sin replace → 422 y no toca los existentes.
        $this->postJson(route('brands.onboarding.apply.pillars', $this->brand), $payload)
            ->assertStatus(422);
        $this->assertSame(['Viejo'], $this->brand->pillars()->pluck('name')->all());

        // Con replace → borra y crea.
        $this->postJson(route('brands.onboarding.apply.pillars', $this->brand), [
            ...$payload,
            'replace' => true,
        ])->assertRedirect();

        $names = $this->brand->pillars()->orderBy('name')->pluck('name')->all();
        $this->assertSame(['Nuevo A', 'Nuevo B'], $names);
    }

    public function test_apply_strategies_only_persists_plan_networks(): void
    {
        $this->actingAsAdminFor($this->brand);

        $this->post(route('brands.onboarding.apply.strategies', $this->brand), [
            'strategies' => [
                ['network' => 'instagram', 'posts_per_week' => 3, 'formats' => ['image', 'reel'], 'focus' => 'Visual'],
                // linkedin solo admite image: reel se recorta.
                ['network' => 'linkedin', 'posts_per_week' => 2, 'formats' => ['image', 'reel'], 'focus' => 'B2B'],
                // tiktok no está en el plan (solo ig+li): se ignora.
                ['network' => 'tiktok', 'posts_per_week' => 5, 'formats' => ['video'], 'focus' => 'Viral'],
            ],
        ])->assertRedirect();

        $strategies = NetworkStrategy::withoutGlobalScopes()
            ->where('brand_id', $this->brand->id)
            ->get()
            ->keyBy(fn ($s) => $s->network->value);

        $this->assertSame(['instagram', 'linkedin'], $strategies->keys()->sort()->values()->all());
        $this->assertSame(3, $strategies['instagram']->posts_per_week);
        $this->assertSame(['image', 'reel'], $strategies['instagram']->preferred_formats);
        $this->assertSame(['image'], $strategies['linkedin']->preferred_formats);
        $this->assertTrue($this->brand->fresh()->onboarding_proposal['applied']['strategies'] ?? false);
    }

    public function test_apply_palette_updates_brand_palette(): void
    {
        $this->actingAsAdminFor($this->brand);

        $this->post(route('brands.onboarding.apply.palette', $this->brand), [
            'palette' => [
                'dark' => '#111111',
                'light' => '#eeeeee',
                'accent' => '#aa3300',
                'secondary' => '#224466',
            ],
        ])->assertRedirect();

        $this->brand->refresh();
        $this->assertSame('#111111', $this->brand->palette['dark']);
        $this->assertSame('#EEEEEE', $this->brand->palette['light']);
        $this->assertTrue($this->brand->onboarding_proposal['applied']['palette'] ?? false);
    }

    public function test_apply_palette_validates_hex(): void
    {
        $this->actingAsAdminFor($this->brand);

        $this->postJson(route('brands.onboarding.apply.palette', $this->brand), [
            'palette' => [
                'dark' => 'rojo',
                'light' => '#EEEEEE',
                'accent' => '#AA3300',
                'secondary' => '#224466',
            ],
        ])->assertStatus(422);
    }

    public function test_onboarded_at_is_set_when_all_sections_are_applied(): void
    {
        $this->actingAsAdminFor($this->brand);
        $proposal = $this->proposal();
        $proposal['applied'] = ['profile' => true, 'pillars' => true, 'strategies' => true];
        $this->brand->forceFill(['onboarding_proposal' => $proposal])->save();

        $this->post(route('brands.onboarding.apply.palette', $this->brand), [
            'palette' => [
                'dark' => '#111111',
                'light' => '#EEEEEE',
                'accent' => '#AA3300',
                'secondary' => '#224466',
            ],
        ])->assertRedirect();

        $this->assertNotNull($this->brand->fresh()->onboarded_at);
    }

    public function test_operators_cannot_apply_proposal_sections(): void
    {
        $this->actingAsOperatorFor($this->brand);

        $this->post(route('brands.onboarding.apply.profile', $this->brand), [])->assertForbidden();
        $this->post(route('brands.onboarding.apply.pillars', $this->brand), [])->assertForbidden();
        $this->post(route('brands.onboarding.apply.strategies', $this->brand), [])->assertForbidden();
        $this->post(route('brands.onboarding.apply.palette', $this->brand), [])->assertForbidden();
    }
}
