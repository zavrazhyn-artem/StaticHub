<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\RaidEvent;
use App\Services\CalendarService;
use App\Tasks\Raid\CreateRaidEventTask;
use App\Tasks\StaticGroup\FetchUserDefaultStaticTask;

class ScheduleService
{
    /**
     * ScheduleService constructor.
     *
     * @param CalendarService $calendarService
     * @param FetchUserDefaultStaticTask $fetchUserDefaultStaticTask
     * @param CreateRaidEventTask $createRaidEventTask
     */
    public function __construct(
        private readonly CalendarService            $calendarService,
        private readonly FetchUserDefaultStaticTask $fetchUserDefaultStaticTask,
        private readonly CreateRaidEventTask        $createRaidEventTask,
    ) {
    }

    /**
     * Get the schedule index payload for a user.
     *
     * @param int $year
     * @param int $month
     * @param int $userId
     * @return array
     */
    public function buildSchedulePayload(int $year, int $month, int $userId): array
    {
        $static = $this->fetchUserDefaultStaticTask->run($userId);

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
     * @throws \Exception
     */
    public function executeEventCreation(array $data, int $userId): RaidEvent
    {
        return $this->createRaidEventTask->run($data, $userId);
    }

    /**
     * Update an existing raid event.
     *
     * @param RaidEvent $event
     * @param array $data
     * @param int $userId
     * @return RaidEvent
     * @throws \Exception
     */
    public function executeEventUpdate(RaidEvent $event, array $data, int $userId): RaidEvent
    {
        // For simplicity, we can use the same logic for parsing times, but directly update the model.
        // We'll extract time parsing logic into a helper or similar if it gets complex.
        $timezone = $data['timezone'] ?? 'UTC';
        $date = $data['date'] ?? $event->start_time->format('Y-m-d');

        $startStr = $date . ' ' . $data['start_time'];
        $endStr = $date . ' ' . $data['end_time'];

        $startTime = \Illuminate\Support\Carbon::parse($startStr, $timezone)->setTimezone('UTC');
        $endTime = \Illuminate\Support\Carbon::parse($endStr, $timezone)->setTimezone('UTC');

        if ($endTime->lessThan($startTime)) {
            $endTime->addDay();
        }

        $event->update([
            'start_time' => $startTime,
            'end_time' => $endTime,
            'description' => $data['description'] ?? $event->description,
        ]);

        return $event;
    }

    /**
     * Delete a raid event.
     *
     * @param RaidEvent $event
     * @param int $userId
     * @return void
     */
    public function executeEventDeletion(RaidEvent $event, int $userId): void
    {
        // Only allow static owners to delete
        if ($userId !== (int)$event->static->owner_id) {
            abort(403, 'Unauthorized action.');
        }

        $event->delete();
    }
}
