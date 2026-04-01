<?php

namespace App\Services;

use App\Models\RaidEvent;
use App\Models\StaticGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ScheduleService
{
    private CalendarService $calendarService;

    public function __construct(CalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Get the schedule index data for a user.
     *
     * @param int $year
     * @param int $month
     * @param int $userId
     * @return array
     */
    public function getScheduleData(int $year, int $month, int $userId): array
    {
        $static = StaticGroup::query()->firstForUser($userId);

        if (!$static) {
            abort(404, 'No static group found.');
        }

        $calendarData = $this->calendarService->buildMonthGrid($year, $month, $static->id);

        return array_merge($calendarData, [
            'static' => $static,
        ]);
    }

    /**
     * Create a new raid event.
     *
     * @param array $data
     * @param int $userId
     * @return RaidEvent
     */
    public function createEvent(array $data, int $userId): RaidEvent
    {
        $static = StaticGroup::findOrFail($data['static_id']);

        if (!$this->isUserMemberOfStatic($static, $userId)) {
            abort(403);
        }

        $startTime = Carbon::parse($data['date'] . ' ' . $data['time']);

        return RaidEvent::create([
            'static_id' => $data['static_id'],
            'title' => $data['title'],
            'start_time' => $startTime,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Check if a user is a member of a static group.
     *
     * @param StaticGroup $static
     * @param int $userId
     * @return bool
     */
    private function isUserMemberOfStatic(StaticGroup $static, int $userId): bool
    {
        return $static->members()->where('user_id', $userId)->exists();
    }
}
