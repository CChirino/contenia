<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCalendarEventRequest;
use App\Http\Requests\Settings\UpdateCalendarEventRequest;
use App\Models\Brand;
use App\Models\CalendarEvent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CalendarEventController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Calendar', [
            'events' => CalendarEvent::orderBy('date')->get(),
            'isAdmin' => request()->user()->isAdmin(),
        ]);
    }

    public function store(StoreCalendarEventRequest $request): RedirectResponse
    {
        // El cliente no decide brand_id: admin + global=true → feriado global;
        // cualquier otro caso → evento de la marca activa.
        $isGlobal = $request->user()->isAdmin() && $request->boolean('global');

        CalendarEvent::create([
            ...$request->safe()->except('global'),
            'brand_id' => $isGlobal ? null : Brand::current()->id,
        ]);

        return redirect()->route('settings.calendar.index');
    }

    public function update(UpdateCalendarEventRequest $request, Brand $brand, CalendarEvent $event): RedirectResponse
    {
        // Los feriados globales solo los edita un admin.
        abort_if($event->brand_id === null && ! $request->user()->isAdmin(), 403);

        $event->update($request->validated());

        return redirect()->route('settings.calendar.index');
    }

    public function destroy(Brand $brand, CalendarEvent $event): RedirectResponse
    {
        abort_if($event->brand_id === null && ! request()->user()->isAdmin(), 403);

        $event->delete();

        return redirect()->route('settings.calendar.index');
    }
}
