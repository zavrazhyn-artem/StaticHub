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

class SwapRsvpSpecJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $eventId,
        private readonly int $userId,
        private readonly int $specId
    ) {
    }

    public function handle(DiscordMessageService $discordMessageService): void
    {
        $event = Event::find($this->eventId);

        if (!$event || $event->raid_started) {
            return;
        }

        $attendance = RaidAttendance::where('event_id', $this->eventId)
            ->whereIn('character_id', function ($query) {
                $query->select('id')
                    ->from('characters')
                    ->where('user_id', $this->userId);
            })
            ->first();

        if (!$attendance) {
            return;
        }

        $attendance->update(['spec_id' => $this->specId]);

        $discordMessageService->sendOrUpdateRaidAnnouncement($event);
    }
}
