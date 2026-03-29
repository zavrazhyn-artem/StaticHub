<?php

namespace App\Http\Controllers;

use App\Models\RaidEvent;
use App\Services\RaidAttendanceService;
use App\Services\DiscordMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RaidEventController extends Controller
{
    protected RaidAttendanceService $attendanceService;
    protected DiscordMessageService $discordMessageService;

    public function __construct(
        RaidAttendanceService $attendanceService,
        DiscordMessageService $discordMessageService
    ) {
        $this->attendanceService = $attendanceService;
        $this->discordMessageService = $discordMessageService;
    }

    /**
     * Display the specified raid event.
     */
    public function show(RaidEvent $event)
    {
        $user = Auth::user();

        // Get all characters of the current user belonging to this static
        $userCharacters = $user->characters()
            ->whereHas('statics', function ($query) use ($event) {
                $query->where('statics.id', $event->static_id);
            })
            ->get();

        // Find the character the user has already RSVP'd with for this event
        $currentAttendance = $event->characters()
            ->where('user_id', $user->id)
            ->first()?->pivot;

        // Determine which character should be selected by default in the form
        $selectedCharacterId = $currentAttendance ? $currentAttendance->character_id : null;

        if (!$selectedCharacterId && $userCharacters->isNotEmpty()) {
            foreach ($userCharacters as $char) {
                $isMain = $char->statics()
                    ->where('statics.id', $event->static_id)
                    ->where('character_static.role', 'main')
                    ->exists();

                if ($isMain) {
                    $selectedCharacterId = $char->id;
                    break;
                }
            }

            if (!$selectedCharacterId) {
                $selectedCharacterId = $userCharacters->first()->id;
            }
        }

        $rosterData = $this->attendanceService->getGroupedRoster($event);
        $mainRoster = $rosterData['mainRoster'];
        $absentRoster = $rosterData['absentRoster'];

        return view('schedule.show', compact(
            'event',
            'mainRoster',
            'absentRoster',
            'userCharacters',
            'currentAttendance',
            'selectedCharacterId'
        ));
    }

    /**
     * Store RSVP for a specific character.
     */
    public function rsvp(Request $request, RaidEvent $event)
    {
        \Log::info('RSVP Request received', [
            'event_id' => $event->id,
            'user_id' => Auth::id(),
            'data' => $request->all()
        ]);

        $validated = $request->validate([
            'character_id' => 'required|exists:characters,id',
            'status' => 'required|in:present,absent,tentative,late',
            'comment' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        // Verify the character belongs to the user and is in the static
        $character = $user->characters()
            ->where('characters.id', $validated['character_id'])
            ->whereHas('statics', function ($query) use ($event) {
                $query->where('statics.id', $event->static_id);
            })->first();

        if (!$character) {
            \Log::warning('RSVP failed: character not found or not in static', [
                'character_id' => $validated['character_id'],
                'user_id' => $user->id,
                'static_id' => $event->static_id
            ]);
            return back()->withErrors(['character_id' => 'Invalid character selected.']);
        }

        // Before updating, we should probably remove any existing attendance for this user at this event
        // (if we allow changing the character)
        $event->characters()->where('user_id', $user->id)->detach();

        try {
            $this->attendanceService->updateAttendance($event, $character, $validated['status'], $validated['comment']);

            // If the event is already posted to Discord, update the message
            if ($event->discord_message_id) {
                $this->discordMessageService->sendOrUpdateRaidAnnouncement($event);
            }

            \Log::info('RSVP success', ['event_id' => $event->id, 'character_id' => $character->id]);
        } catch (\Exception $e) {
            \Log::error('RSVP Service error', ['error' => $e->getMessage()]);
            return back()->withErrors(['rsvp' => 'Failed to save attendance: ' . $e->getMessage()]);
        }

        return back()->with('success', 'Attendance status updated!');
    }

    /**
     * Announce the raid event to Discord.
     */
    public function announceToDiscord(RaidEvent $event)
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
