<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleRequest;
use App\Services\Raid\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService
    ) {
    }

    /**
     * Display the schedule.
     */
    public function index(Request $request): View
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $scheduleData = $this->scheduleService->buildSchedulePayload($year, $month, Auth::id());

        return view('schedule.index', $scheduleData);
    }

    /**
     * Store a new event in the schedule.
     */
    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        Log::info('Creating event', $request->validated());
        $this->scheduleService->executeEventCreation($request->validated(), Auth::id());

        return redirect()->back()->with('success', 'Event created successfully!');
    }

    /**
     * Update an event in the schedule.
     */
    public function update(StoreScheduleRequest $request, \App\Models\RaidEvent $event): RedirectResponse
    {
        Log::info('Updating event', $request->validated());
        $this->scheduleService->executeEventUpdate($event, $request->validated(), Auth::id());

        return redirect()->back()->with('success', 'Event updated successfully!');
    }

    /**
     * Delete an event from the schedule.
     */
    public function destroy(\App\Models\RaidEvent $event): RedirectResponse
    {
        $this->scheduleService->executeEventDeletion($event, Auth::id());

        return redirect()->route('schedule.index')->with('success', 'Event deleted successfully!');
    }
}
