<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreScheduleRequest;
use App\Models\Event;
use App\Models\StaticGroup;
use App\Services\Raid\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly EventService $eventService
    ) {
    }

    /**
     * Display the schedule.
     */
    public function index(Request $request): View
    {
        $year  = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $scheduleData = $this->eventService->buildSchedulePayload($year, $month, Auth::id());

        return view('schedule.index', $scheduleData);
    }

    /**
     * Store a new event in the schedule.
     */
    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $static = StaticGroup::findOrFail($request->validated('static_id'));
        Gate::authorize('canManageSchedule', $static);

        Log::info('Creating event', $request->validated());
        $this->eventService->createEvent($request->validated(), Auth::id());

        return redirect()->back()->with('success', 'Event created successfully!');
    }

    /**
     * Update an event in the schedule.
     */
    public function update(StoreScheduleRequest $request, Event $event): RedirectResponse
    {
        Gate::authorize('canManageSchedule', $event->static);

        if ($event->raid_started) {
            return redirect()->back()->withErrors(['event' => __('Event already started. Changes are not allowed.')]);
        }

        Log::info('Updating event', $request->validated());
        $this->eventService->executeEventUpdate($event, $request->validated(), Auth::id());

        return redirect()->back()->with('success', 'Event updated successfully!');
    }

    /**
     * Delete an event from the schedule.
     */
    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('canManageSchedule', $event->static);

        if ($event->raid_started) {
            return redirect()->back()->withErrors(['event' => __('Event already started. Changes are not allowed.')]);
        }

        $this->eventService->executeEventDeletion($event);

        return redirect()->route('schedule.index')->with('success', 'Event deleted successfully!');
    }
}
