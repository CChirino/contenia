<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_events_visible_in_every_brand(): void
    {
        $global = CalendarEvent::factory()->create(['brand_id' => null]);
        $brandB = Brand::factory()->create();

        app()->instance('brand', Brand::firstOrFail());
        $this->assertTrue(CalendarEvent::pluck('id')->contains($global->id));

        app()->instance('brand', $brandB);
        $this->assertTrue(CalendarEvent::pluck('id')->contains($global->id));
    }

    public function test_brand_event_not_visible_in_other_brand(): void
    {
        $brandA = Brand::where('slug', 'sabere')->firstOrFail();
        $brandB = Brand::factory()->create();

        $ownEvent = CalendarEvent::factory()->create(['brand_id' => $brandA->id]);
        $otherEvent = CalendarEvent::factory()->create(['brand_id' => $brandB->id]);

        app()->instance('brand', $brandA);
        $this->assertTrue(CalendarEvent::pluck('id')->contains($ownEvent->id));
        $this->assertFalse(CalendarEvent::pluck('id')->contains($otherEvent->id));
    }

    public function test_store_brand_event_assigns_active_brand(): void
    {
        $this->actingAsOperatorFor();
        $brand = Brand::firstOrFail();

        $this->post(route('settings.calendar.store'), [
            'date' => '2026-11-01',
            'name' => 'Evento propio',
            'global' => false,
        ])->assertRedirect();

        $event = CalendarEvent::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame($brand->id, $event->brand_id);
    }

    public function test_operator_cannot_create_global_event_even_with_flag(): void
    {
        $this->actingAsOperatorFor();
        $brand = Brand::firstOrFail();

        $this->post(route('settings.calendar.store'), [
            'date' => '2026-11-02',
            'name' => 'Intento global',
            'global' => true,
        ])->assertRedirect();

        $event = CalendarEvent::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame($brand->id, $event->brand_id);
    }

    public function test_admin_can_create_global_event(): void
    {
        $this->actingAsAdminFor();

        $this->post(route('settings.calendar.store'), [
            'date' => '2026-11-03',
            'name' => 'Feriado nuevo',
            'global' => true,
        ])->assertRedirect();

        $event = CalendarEvent::withoutGlobalScopes()->latest('id')->first();
        $this->assertNull($event->brand_id);
    }

    public function test_operator_cannot_edit_or_delete_global_event(): void
    {
        $global = CalendarEvent::factory()->create(['brand_id' => null]);

        $this->actingAsOperatorFor();

        $this->put(route('settings.calendar.update', ['event' => $global]), [
            'date' => '2026-11-04',
            'name' => 'Editado',
        ])->assertForbidden();

        $this->delete(route('settings.calendar.destroy', ['event' => $global]))
            ->assertForbidden();
    }

    public function test_admin_can_edit_and_delete_global_event(): void
    {
        $global = CalendarEvent::factory()->create(['brand_id' => null]);

        $this->actingAsAdminFor();

        $this->put(route('settings.calendar.update', ['event' => $global]), [
            'date' => '2026-11-05',
            'name' => 'Feriado editado',
        ])->assertRedirect();
        $this->assertSame('Feriado editado', $global->fresh()->name);

        $this->delete(route('settings.calendar.destroy', ['event' => $global]))
            ->assertRedirect();
        $this->assertDatabaseMissing('calendar_events', ['id' => $global->id]);
    }
}
