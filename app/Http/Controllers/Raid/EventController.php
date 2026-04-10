<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;

use App\Models\Event;
use App\Services\Raid\EventService;
use App\Services\Raid\EventPayloadService;
use App\Services\Raid\EncounterRosterService;
use App\Services\Discord\DiscordMessageService;
use App\Http\Requests\RsvpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(
        protected EventService $eventService,
        protected EventPayloadService $eventPayloadService,
        protected EncounterRosterService $encounterRosterService,
        protected DiscordMessageService $discordMessageService,
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

    /**
     * Bulk update encounter roster assignments.
     */
    public function updateEncounterRoster(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('canManageSchedule', $event->static);

        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.encounter_slug' => 'required|string',
            'assignments.*.character_id' => 'required|integer|exists:characters,id',
            'assignments.*.selection_status' => 'required|in:selected,queued,benched',
            'assignments.*.position_order' => 'integer|min:0',
        ]);

        $this->encounterRosterService->bulkUpdateEncounterRoster($event, $validated['assignments']);

        return response()->json(['success' => true]);
    }

    /**
     * Assign a single character to an encounter.
     */
    public function assignEncounterCharacter(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('canManageSchedule', $event->static);

        $validated = $request->validate([
            'encounter_slug' => 'required|string',
            'character_id' => 'required|integer|exists:characters,id',
            'selection_status' => 'required|in:selected,queued,benched',
            'position_order' => 'integer|min:0',
        ]);

        $roster = $this->encounterRosterService->assignCharacter(
            $event->id,
            $validated['encounter_slug'],
            $validated['character_id'],
            $validated['selection_status'],
            $validated['position_order'] ?? 0,
        );

        return response()->json(['success' => true, 'roster' => $roster]);
    }

    /**
     * Remove a character from an encounter roster.
     */
    public function removeEncounterCharacter(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('canManageSchedule', $event->static);

        $validated = $request->validate([
            'encounter_slug' => 'required|string',
            'character_id' => 'required|integer|exists:characters,id',
        ]);

        $this->encounterRosterService->removeCharacter(
            $event->id,
            $validated['encounter_slug'],
            $validated['character_id'],
        );

        return response()->json(['success' => true]);
    }

}
