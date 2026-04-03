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

        $startTime = Carbon::parse($data['date'] . ' ' . $data['time']);

        return RaidEvent::create([
            'static_id' => $data['static_id'],
            'title' => $data['title'],
            'start_time' => $startTime,
            'description' => $data['description'] ?? null,
        ]);
    }
}
