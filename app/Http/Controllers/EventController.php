<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Raid\EventService;
use App\Services\Raid\EventPayloadService;
use App\Services\Discord\DiscordMessageService;
use App\Http\Requests\RsvpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(
        protected EventService $eventService,
        protected EventPayloadService $eventPayloadService,
        protected DiscordMessageService $discordMessageService
    ) {}

    /**
     * Display the specified raid event.
     */
    public function show(Event $event): View
    {
        $data = $this->eventPayloadService->buildEventShowPayload($event, Auth::user());

        return view('schedule.show', $data);
    }

    /**
     * Store RSVP for a specific character.
     */
    public function rsvp(RsvpRequest $request, Event $event): RedirectResponse
    {
        if ($event->raid_started) {
            return back()->withErrors(['character_id' => __('Event already started. Changes are not allowed.')]);
        }

        $success = $this->eventService->executeRsvp($event, Auth::user(), $request->validated());

        if (!$success) {
            return back()->withErrors(['character_id' => __('Invalid character selected.')]);
        }

        if ($event->discord_message_id) {
            $this->discordMessageService->sendOrUpdateRaidAnnouncement($event);
        }

        return back()->with('success', __('Attendance status updated!'));
    }

    /**
     * Announce the raid event to Discord.
     */
    public function announceToDiscord(Event $event): RedirectResponse
    {
        Gate::authorize('canAnnounceToDiscord', $event->static);

        $success = $this->discordMessageService->sendOrUpdateRaidAnnouncement($event);

        if ($success) {
            return back()->with('success', __('Raid event posted to Discord!'));
        }

        return back()->with('error', __('Failed to post to Discord. Check if the channel ID is configured in settings.'));
    }
}
