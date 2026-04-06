<?php

declare(strict_types=1);

namespace App\Jobs\Discord;

use App\Models\Event;
use App\Services\Discord\DiscordMessageService;
use App\Services\Raid\RaidAttendanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SwapRsvpSpecJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $eventId,
        private readonly int $userId,
        private readonly int $specId
    ) {
    }

    public function handle(RaidAttendanceService $attendanceService, DiscordMessageService $discordMessageService): void
    {
        $event = Event::query()->findById($this->eventId);

        if (!$event || $event->raid_started) {
            return;
        }

        $attendanceService->swapSpec($this->eventId, $this->userId, $this->specId);
        $discordMessageService->sendOrUpdateRaidAnnouncement($event);
    }
}
