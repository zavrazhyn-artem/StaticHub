<?php

namespace App\Http\Controllers;

use App\Models\RaidEvent;
use App\Services\Raid\RaidEventService;
use App\Services\Discord\DiscordMessageService;
use App\Http\Requests\RsvpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RaidEventController extends Controller
{
    public function __construct(
        protected RaidEventService $raidEventService,
        protected DiscordMessageService $discordMessageService
    ) {}

    /**
     * Display the specified raid event.
     */
    public function show(RaidEvent $event): View
    {
        $data = $this->raidEventService->buildEventShowPayload($event, Auth::user());

        return view('schedule.show', $data);
    }

    /**
     * Store RSVP for a specific character.
     */
    public function rsvp(RsvpRequest $request, RaidEvent $event): RedirectResponse
    {
        $success = $this->raidEventService->executeRsvp($event, Auth::user(), $request->validated());

        if (!$success) {
            return back()->withErrors(['character_id' => 'Invalid character selected.']);
        }

        // If the event is already posted to Discord, update the message
        if ($event->discord_message_id) {
            $this->discordMessageService->sendOrUpdateRaidAnnouncement($event);
        }

        return back()->with('success', 'Attendance status updated!');
    }

    /**
     * Announce the raid event to Discord.
     */
    public function announceToDiscord(RaidEvent $event): RedirectResponse
    {
        // Only allow static owners to announce
        if (Auth::user()->id !== $event->static->owner_id) {
            return back()->with('error', 'Only the static owner can post to Discord.');
        }

        $success = $this->discordMessageService->sendOrUpdateRaidAnnouncement($event);

        if ($success) {
            return back()->with('success', 'Raid event posted to Discord!');
        }

        return back()->with('error', 'Failed to post to Discord. Check if the channel ID is configured in settings.');
    }
}
