<?php

namespace Tests\Feature\Settings;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $event = CalendarEvent::factory()->create();

        $this->get(route('settings.calendar.index'))->assertRedirect(route('login'));
        $this->post(route('settings.calendar.store'), [])->assertRedirect(route('login'));
        $this->put(route('settings.calendar.update', $event), [])->assertRedirect(route('login'));
        $this->delete(route('settings.calendar.destroy', $event))->assertRedirect(route('login'));
    }

    public function test_index_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.calendar.index'))
            ->assertOk();
    }

    public function test_store_requires_date(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('settings.calendar.store'), [
                'name' => 'Sin fecha',
            ])
            ->assertSessionHasErrors(['date']);
    }

    public function test_store_creates_event(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('settings.calendar.store'), [
                'date' => '2026-01-15',
                'name' => 'Día del Maestro',
                'description' => 'Feriado nacional',
                'recurring' => true,
                'no_posting' => false,
            ])
            ->assertRedirect(route('settings.calendar.index'));

        $this->assertDatabaseHas('calendar_events', [
            'name' => 'Día del Maestro',
            'recurring' => true,
        ]);
    }

    public function test_update_modifies_event(): void
    {
        $event = CalendarEvent::factory()->create(['no_posting' => false]);

        $this->actingAs(User::factory()->create())
            ->put(route('settings.calendar.update', $event), [
                'date' => '2026-12-24',
                'name' => 'Nochebuena',
                'description' => null,
                'recurring' => true,
                'no_posting' => true,
            ])
            ->assertRedirect(route('settings.calendar.index'));

        $event->refresh();
        $this->assertSame('Nochebuena', $event->name);
        $this->assertTrue($event->no_posting);
    }

    public function test_destroy_deletes_event(): void
    {
        $event = CalendarEvent::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('settings.calendar.destroy', $event))
            ->assertRedirect(route('settings.calendar.index'));

        $this->assertDatabaseMissing('calendar_events', ['id' => $event->id]);
    }
}
