<?php

namespace Tests\Feature\Settings;

use App\Enums\GuidelineSource;
use App\Enums\GuidelineStatus;
use App\Models\Brand;
use App\Models\BrandGuideline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Pautas aprendidas (spec §8.2): CRUD del operador y transiciones de
 * estado. Solo las `active` llegan al prompt; el resto espera decisión
 * humana. Las transiciones inválidas devuelven 422.
 */
class GuidelinesTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brand = Brand::where('slug', 'sabere')->first();
    }

    private function guideline(array $attrs = []): BrandGuideline
    {
        return BrandGuideline::factory()->create([
            'brand_id' => $this->brand->id,
            ...$attrs,
        ]);
    }

    /** URL de una acción sobre una pauta (brand va por defecto en tests). */
    private function routeTo(string $name, BrandGuideline $guideline): string
    {
        return route("settings.guidelines.{$name}", ['guideline' => $guideline]);
    }

    public function test_index_lists_guidelines_of_the_active_brand(): void
    {
        $this->actingAsOperatorFor($this->brand);
        $this->guideline(['text' => 'Pauta propia', 'status' => GuidelineStatus::Active]);

        $other = Brand::factory()->create();
        BrandGuideline::factory()->create([
            'brand_id' => $other->id,
            'text' => 'Pauta de otra marca',
            'status' => GuidelineStatus::Active,
        ]);

        $this->get(route('settings.guidelines.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Guidelines')
                ->has('guidelines', 1)
                ->where('guidelines.0.text', 'Pauta propia')
                ->where('guidelines.0.status', 'active')
            );
    }

    public function test_store_creates_an_active_operator_guideline(): void
    {
        $this->actingAsOperatorFor($this->brand);

        $this->post(route('settings.guidelines.store'), [
            'text' => 'Nunca uses más de un hashtag genérico.',
        ])->assertRedirect(route('settings.guidelines.index'));

        $guideline = BrandGuideline::first();
        $this->assertSame('Nunca uses más de un hashtag genérico.', $guideline->text);
        $this->assertSame(GuidelineSource::Operator, $guideline->source);
        $this->assertSame(GuidelineStatus::Active, $guideline->status);
        $this->assertSame($this->brand->id, $guideline->brand_id);
    }

    public function test_store_validates_max_200_chars(): void
    {
        $this->actingAsOperatorFor($this->brand);

        $this->post(route('settings.guidelines.store'), [
            'text' => str_repeat('a', 201),
        ])->assertSessionHasErrors('text');

        $this->assertSame(0, BrandGuideline::count());
    }

    public function test_activate_moves_pending_to_active(): void
    {
        $this->actingAsOperatorFor($this->brand);
        $guideline = $this->guideline(['status' => GuidelineStatus::Pending]);

        $this->post($this->routeTo('activate', $guideline))->assertRedirect();
        $this->assertSame(GuidelineStatus::Active, $guideline->fresh()->status);
    }

    public function test_activate_rejects_non_pending_statuses(): void
    {
        $this->actingAsOperatorFor($this->brand);

        foreach ([GuidelineStatus::Active, GuidelineStatus::PendingRetire, GuidelineStatus::Retired, GuidelineStatus::Discarded] as $status) {
            $guideline = $this->guideline(['status' => $status]);
            $this->post($this->routeTo('activate', $guideline))->assertStatus(422);
            $this->assertSame($status, $guideline->fresh()->status);
        }
    }

    public function test_discard_moves_pending_to_discarded(): void
    {
        $this->actingAsOperatorFor($this->brand);
        $guideline = $this->guideline(['status' => GuidelineStatus::Pending]);

        $this->post($this->routeTo('discard', $guideline))->assertRedirect();
        $this->assertSame(GuidelineStatus::Discarded, $guideline->fresh()->status);

        $active = $this->guideline(['status' => GuidelineStatus::Active]);
        $this->post($this->routeTo('discard', $active))->assertStatus(422);
    }

    public function test_retire_moves_active_and_pending_retire_to_retired(): void
    {
        $this->actingAsOperatorFor($this->brand);

        $active = $this->guideline(['status' => GuidelineStatus::Active]);
        $this->post($this->routeTo('retire', $active))->assertRedirect();
        $this->assertSame(GuidelineStatus::Retired, $active->fresh()->status);

        $pendingRetire = $this->guideline(['status' => GuidelineStatus::PendingRetire]);
        $this->post($this->routeTo('retire', $pendingRetire))->assertRedirect();
        $this->assertSame(GuidelineStatus::Retired, $pendingRetire->fresh()->status);

        $pending = $this->guideline(['status' => GuidelineStatus::Pending]);
        $this->post($this->routeTo('retire', $pending))->assertStatus(422);
    }

    public function test_keep_moves_pending_retire_back_to_active(): void
    {
        $this->actingAsOperatorFor($this->brand);
        $guideline = $this->guideline(['status' => GuidelineStatus::PendingRetire]);

        $this->post($this->routeTo('keep', $guideline))->assertRedirect();
        $this->assertSame(GuidelineStatus::Active, $guideline->fresh()->status);

        $pending = $this->guideline(['status' => GuidelineStatus::Pending]);
        $this->post($this->routeTo('keep', $pending))->assertStatus(422);
    }

    public function test_update_edits_text_of_live_guidelines_only(): void
    {
        $this->actingAsOperatorFor($this->brand);
        $guideline = $this->guideline(['status' => GuidelineStatus::Active]);

        $this->put($this->routeTo('update', $guideline), [
            'text' => 'Texto corregido',
        ])->assertRedirect();
        $this->assertSame('Texto corregido', $guideline->fresh()->text);

        $this->put($this->routeTo('update', $guideline), [
            'text' => str_repeat('b', 201),
        ])->assertSessionHasErrors('text');

        $retired = $this->guideline(['status' => GuidelineStatus::Retired]);
        $this->put($this->routeTo('update', $retired), [
            'text' => 'No debería editarse',
        ])->assertStatus(422);
    }

    public function test_destroy_only_allows_pending_or_discarded(): void
    {
        $this->actingAsOperatorFor($this->brand);

        $pending = $this->guideline(['status' => GuidelineStatus::Pending]);
        $this->delete($this->routeTo('destroy', $pending))->assertRedirect();
        $this->assertNull($pending->fresh());

        $discarded = $this->guideline(['status' => GuidelineStatus::Discarded]);
        $this->delete($this->routeTo('destroy', $discarded))->assertRedirect();
        $this->assertNull($discarded->fresh());

        foreach ([GuidelineStatus::Active, GuidelineStatus::PendingRetire, GuidelineStatus::Retired] as $status) {
            $guideline = $this->guideline(['status' => $status]);
            $this->delete($this->routeTo('destroy', $guideline))->assertStatus(422);
            $this->assertNotNull($guideline->fresh());
        }
    }

    public function test_guidelines_of_another_brand_are_not_reachable(): void
    {
        $this->actingAsOperatorFor($this->brand);

        $other = Brand::factory()->create();
        $foreign = BrandGuideline::factory()->create([
            'brand_id' => $other->id,
            'status' => GuidelineStatus::Pending,
        ]);

        // El route-binding filtra por la marca activa: pauta ajena → 404.
        $this->post($this->routeTo('activate', $foreign))->assertNotFound();
        $this->put($this->routeTo('update', $foreign), ['text' => 'x'])->assertNotFound();
        $this->delete($this->routeTo('destroy', $foreign))->assertNotFound();
    }
}
