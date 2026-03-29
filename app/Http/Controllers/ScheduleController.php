<?php

namespace App\Http\Controllers;

use App\Models\RaidEvent;
use App\Models\StaticGroup;
use App\Services\CalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    protected CalendarService $calendarService;

    public function __construct(CalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    public function index(Request $request)
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $static = Auth::user()->statics()->first();

        if (!$static) {
            abort(404, 'No static group found.');
        }

        $calendarData = $this->calendarService->buildMonthGrid($year, $month, $static->id);

        return view('schedule.index', array_merge($calendarData, [
            'static' => $static,
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'static_id' => 'required|exists:statics,id',
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'description' => 'nullable|string',
        ]);

        // Check authorization (user must belong to the static)
        $static = StaticGroup::findOrFail($validated['static_id']);
        if (!$static->members()->where('user_id', Auth::id())->exists()) {
            abort(403);
        }

        $startTime = Carbon::parse($validated['date'] . ' ' . $validated['time']);

        RaidEvent::create([
            'static_id' => $validated['static_id'],
            'title' => $validated['title'],
            'start_time' => $startTime,
            'description' => $validated['description'],
        ]);

        return redirect()->back()->with('success', 'Event created successfully!');
    }
}
