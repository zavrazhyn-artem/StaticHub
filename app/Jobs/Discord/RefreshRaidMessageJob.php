<?php

declare(strict_types=1);

namespace App\Jobs\Discord;

use App\Models\Event;
use App\Services\Discord\DiscordMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshRaidMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $eventId,
    ) {
    }

    public function handle(DiscordMessageService $discordMessageService): void
    {
        $event = Event::query()->findById($this->eventId);

        if (!$event) {
            return;
        }

        $discordMessageService->sendOrUpdateRaidAnnouncement($event);
    }
}
