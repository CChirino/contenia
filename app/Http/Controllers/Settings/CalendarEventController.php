<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCalendarEventRequest;
use App\Http\Requests\Settings\UpdateCalendarEventRequest;
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
        ]);
    }

    public function store(StoreCalendarEventRequest $request): RedirectResponse
    {
        CalendarEvent::create($request->validated());

        return redirect()->route('settings.calendar.index');
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $event): RedirectResponse
    {
        $event->update($request->validated());

        return redirect()->route('settings.calendar.index');
    }

    public function destroy(CalendarEvent $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('settings.calendar.index');
    }
}
