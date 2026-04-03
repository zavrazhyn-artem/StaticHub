<?php

declare(strict_types=1);

namespace App\Jobs\Discord;

use App\Models\Character;
use App\Models\RaidEvent;
use App\Services\Discord\DiscordMessageService;
use App\Services\Raid\RaidAttendanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRsvpInteractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $eventId,
        private readonly int $characterId,
        private readonly ?string $status
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(RaidAttendanceService $attendanceService, DiscordMessageService $discordMessageService): void
    {
        $event = RaidEvent::find($this->eventId);
        $character = Character::find($this->characterId);

        if (!$event || !$character) {
            return;
        }

        $attendanceService->updateAttendance($event, $character, $this->status);
        $discordMessageService->sendOrUpdateRaidAnnouncement($event);
    }
}
