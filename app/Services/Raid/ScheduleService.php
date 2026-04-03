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
}
