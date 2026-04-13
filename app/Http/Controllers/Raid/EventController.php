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

    /**
     * Override attendance status for a character (RL only).
     */
    public function overrideAttendance(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('canManageSchedule', $event->static);

        $validated = $request->validate([
            'character_id' => 'required|integer|exists:characters,id',
            'status' => 'required|in:present,late,tentative,absent',
            'spec_id' => 'nullable|integer|exists:specializations,id',
        ]);

        $updateData = ['status' => $validated['status']];
        if (isset($validated['spec_id'])) {
            $updateData['spec_id'] = $validated['spec_id'];
        }

        $attendance = \App\Models\RaidAttendance::where('event_id', $event->id)
            ->where('character_id', $validated['character_id'])
            ->first();

        if ($attendance) {
            $attendance->update($updateData);
        } else {
            \App\Models\RaidAttendance::create(array_merge([
                'event_id' => $event->id,
                'character_id' => $validated['character_id'],
            ], $updateData));
        }

        return response()->json(['success' => true]);
    }

    /**
     * Update event feature toggles (boss roster, splits).
     */
    public function updateSettings(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('canManageSchedule', $event->static);

        $validated = $request->validate([
            'boss_roster_enabled' => 'sometimes|boolean',
            'split_enabled' => 'sometimes|boolean',
            'split_count' => 'sometimes|integer|min:1|max:4',
            'locked_encounters' => 'sometimes|nullable|array',
            'locked_encounters.*' => 'string',
        ]);

        $event->update($validated);

        return response()->json(['success' => true, 'event' => $event->fresh()]);
    }

    /**
     * Save split raid assignments in bulk.
     * Expects: { assignments: [{ character_id, split_group }] }
     */
    public function saveSplitAssignments(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('canManageSchedule', $event->static);


        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.character_id' => 'required|integer',
            'assignments.*.split_group' => 'required|integer|min:1|max:4',
        ]);

        // Clear existing split assignments
        \App\Models\RaidAttendance::where('event_id', $event->id)
            ->whereNotNull('split_group')
            ->update(['split_group' => null]);

        // Apply new assignments
        foreach ($validated['assignments'] as $assignment) {
            $attendance = \App\Models\RaidAttendance::where('event_id', $event->id)
                ->where('character_id', $assignment['character_id'])
                ->first();

            if ($attendance) {
                $attendance->update(['split_group' => $assignment['split_group']]);
            } else {
                \App\Models\RaidAttendance::create([
                    'event_id' => $event->id,
                    'character_id' => $assignment['character_id'],
                    'status' => 'present',
                    'split_group' => $assignment['split_group'],
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Toggle encounter selection for an event.
     */
    public function toggleEncounter(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('canManageSchedule', $event->static);

        // Bulk save: full list of selected encounters
        if ($request->has('selected_encounters')) {
            $slugs = $request->input('selected_encounters'); // null = all, [] = none, [...] = specific
            $this->eventService->saveSelectedEncounters($event, $slugs);

            return response()->json([
                'success' => true,
                'selected_encounters' => $event->fresh()->selected_encounters,
            ]);
        }

        // Legacy: single toggle
        $validated = $request->validate([
            'encounter_slug' => 'required|string',
            'selected' => 'required|boolean',
        ]);

        $this->eventService->toggleEncounterSelection(
            $event,
            $validated['encounter_slug'],
            $validated['selected'],
        );

        return response()->json([
            'success' => true,
            'selected_encounters' => $event->fresh()->selected_encounters,
        ]);
    }

    /**
     * Assign a plan to an encounter in this event.
     */
    public function assignPlan(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('canManageSchedule', $event->static);

        $validated = $request->validate([
            'encounter_slug' => 'required|string',
            'plan_id' => 'nullable|integer|exists:raid_plans,id',
        ]);

        $plans = $event->assigned_plans ?? [];
        if ($validated['plan_id']) {
            $plans[$validated['encounter_slug']] = $validated['plan_id'];
        } else {
            unset($plans[$validated['encounter_slug']]);
        }
        $event->update(['assigned_plans' => $plans]);

        return response()->json(['success' => true]);
    }
}
