<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleRequest;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    private ScheduleService $scheduleService;

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * Display the schedule.
     */
    public function index(Request $request): View
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $scheduleData = $this->scheduleService->getScheduleData($year, $month, Auth::id());

        return view('schedule.index', $scheduleData);
    }

    /**
     * Store a new event in the schedule.
     */
    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $this->scheduleService->createEvent($request->validated(), Auth::id());

        return redirect()->back()->with('success', 'Event created successfully!');
    }
}
