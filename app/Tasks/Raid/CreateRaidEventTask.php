<?php

declare(strict_types=1);

namespace App\Tasks\Raid;

use App\Models\RaidEvent;
use App\Models\StaticGroup;
use Carbon\Carbon;

class CreateRaidEventTask
{
    /**
     * Create a new raid event with membership validation.
     *
     * @param array $data
     * @param int $userId
     * @return RaidEvent
     * @throws \Exception
     */
    public function run(array $data, int $userId): RaidEvent
    {
        /** @var StaticGroup $static */
        $static = StaticGroup::findOrFail($data['static_id']);

        if (!$static->hasMember($userId)) {
            throw new \Exception('Unauthorized', 403);
        }

        $timezone = $data['timezone'] ?? 'UTC';
        $startTime = Carbon::parse($data['date'] . ' ' . $data['start_time'], $timezone)->setTimezone('UTC');
        $endTime = isset($data['end_time'])
            ? Carbon::parse($data['date'] . ' ' . $data['end_time'], $timezone)->setTimezone('UTC')
            : null;

        // Перевірка на дублікат івенту на цей день для цього статіка
        $dayStart = $startTime->copy()->startOfDay();
        $dayEnd = $startTime->copy()->endOfDay();

        $exists = RaidEvent::where('static_id', $data['static_id'])
            ->whereBetween('start_time', [$dayStart, $dayEnd])
            ->exists();

        if ($exists) {
            throw new \Exception('На цей день вже заплановано івент.', 422);
        }

        // Якщо end_time раніше за start_time (наприклад, рейд після опівночі), додаємо день
        if ($endTime && $endTime->lessThan($startTime)) {
            $endTime->addDay();
        }

        return RaidEvent::create([
            'static_id' => $data['static_id'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'description' => $data['description'] ?? null,
        ]);
    }
}
