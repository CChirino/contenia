<?php

namespace Tests\Feature\Settings;

use App\Models\ContentPillar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPillarTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $pillar = ContentPillar::factory()->create();

        $this->get(route('settings.pillars.index'))->assertRedirect(route('login'));
        $this->post(route('settings.pillars.store'), [])->assertRedirect(route('login'));
        $this->put(route('settings.pillars.update', $pillar), [])->assertRedirect(route('login'));
        $this->delete(route('settings.pillars.destroy', $pillar))->assertRedirect(route('login'));
    }

    public function test_index_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.pillars.index'))
            ->assertOk();
    }

    public function test_store_creates_pillar(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('settings.pillars.store'), [
                'name' => 'Nuevo pilar',
                'description' => 'Descripción',
                'weight' => 30,
            ])
            ->assertRedirect(route('settings.pillars.index'));

        $this->assertDatabaseHas('content_pillars', ['name' => 'Nuevo pilar', 'weight' => 30]);
    }

    public function test_store_validates_weight_range(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('settings.pillars.store'), [
                'name' => 'Nuevo pilar',
                'description' => 'Descripción',
                'weight' => 120,
            ])
            ->assertSessionHasErrors('weight');
    }

    public function test_update_modifies_pillar(): void
    {
        $pillar = ContentPillar::factory()->create(['weight' => 60]);

        $this->actingAs(User::factory()->create())
            ->put(route('settings.pillars.update', $pillar), [
                'name' => 'Pilar renombrado',
                'description' => $pillar->description,
                'weight' => 45,
            ])
            ->assertRedirect(route('settings.pillars.index'));

        $pillar->refresh();
        $this->assertSame('Pilar renombrado', $pillar->name);
        $this->assertSame(45, $pillar->weight);
    }

    public function test_destroy_deletes_pillar(): void
    {
        $pillar = ContentPillar::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('settings.pillars.destroy', $pillar))
            ->assertRedirect(route('settings.pillars.index'));

        $this->assertDatabaseMissing('content_pillars', ['id' => $pillar->id]);
    }
}
