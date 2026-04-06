<?php

declare(strict_types=1);

namespace App\Jobs\Discord;

use App\Models\RaidAttendance;
use App\Models\Event;
use App\Services\Discord\DiscordMessageService;
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
    public function handle(DiscordMessageService $discordMessageService): void
    {
        $event = Event::find($this->eventId);

        if (!$event || $event->raid_started) {
            return;
        }

        // Find the current attendance record for this User and Event.
        // Since attendance is linked to character_id, we need to find which character the user is currently using.
        $attendance = RaidAttendance::where('event_id', $this->eventId)
            ->whereIn('character_id', function ($query) {
                $query->select('id')
                    ->from('characters')
                    ->where('user_id', $this->userId);
            })
            ->first();

        if ($attendance) {
            // Update existing attendance
            $attendance->update([
                'character_id' => $this->characterId,
            ]);
        } else {
            // If no attendance record exists, create one with a 'tentative' status for the new character
            RaidAttendance::create([
                'event_id' => $this->eventId,
                'character_id' => $this->characterId,
                'status' => 'tentative',
            ]);
        }

        // Refresh the public main roster message.
        $discordMessageService->sendOrUpdateRaidAnnouncement($event);
    }
}
