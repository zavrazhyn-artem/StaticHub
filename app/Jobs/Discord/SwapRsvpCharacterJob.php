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

class SwapRsvpCharacterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $eventId,
        private readonly int $userId,
        private readonly int $characterId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(RaidAttendanceService $attendanceService, DiscordMessageService $discordMessageService): void
    {
        $event = Event::query()->findById($this->eventId);

        if (!$event || $event->raid_started) {
            return;
        }

        $attendanceService->swapCharacter($this->eventId, $this->userId, $this->characterId);
        $discordMessageService->sendOrUpdateRaidAnnouncement($event);
    }
}
