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

    public function test_store_requires_total_weight_of_one_hundred(): void
    {
        ContentPillar::factory()->create(['weight' => 60]);

        $user = User::factory()->create();

        // 60 + 30 = 90: rechazado
        $this->actingAs($user)
            ->post(route('settings.pillars.store'), [
                'name' => 'Nuevo pilar',
                'description' => 'Descripción',
                'weight' => 30,
            ])
            ->assertSessionHasErrors('weight');

        // 60 + 40 = 100: aceptado
        $this->actingAs($user)
            ->post(route('settings.pillars.store'), [
                'name' => 'Nuevo pilar',
                'description' => 'Descripción',
                'weight' => 40,
            ])
            ->assertRedirect(route('settings.pillars.index'));

        $this->assertDatabaseHas('content_pillars', ['name' => 'Nuevo pilar', 'weight' => 40]);
    }

    public function test_update_validates_weight_against_other_pillars(): void
    {
        $a = ContentPillar::factory()->create(['weight' => 60]);
        $b = ContentPillar::factory()->create(['weight' => 40]);

        $user = User::factory()->create();

        // b a 50 → total 110: rechazado
        $this->actingAs($user)
            ->put(route('settings.pillars.update', $b), [
                'name' => $b->name,
                'description' => $b->description,
                'weight' => 50,
            ])
            ->assertSessionHasErrors('weight');

        // b a 20 y a se ajusta… solo probamos que un total válido pasa:
        $this->actingAs($user)
            ->put(route('settings.pillars.update', $a), [
                'name' => 'Pilar renombrado',
                'description' => $a->description,
                'weight' => 60,
            ])
            ->assertRedirect(route('settings.pillars.index'));

        $this->assertSame('Pilar renombrado', $a->fresh()->name);
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
