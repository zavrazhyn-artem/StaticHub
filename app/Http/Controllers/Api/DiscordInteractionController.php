<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RaidEvent;
use App\Models\User;
use App\Services\DiscordMessageService;
use App\Services\RaidAttendanceService;
use Illuminate\Http\Request;

class DiscordInteractionController extends Controller
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

    public function handle(Request $request)
    {
        $type = $request->input('type');

        // type 1: PING
        if ($type === 1) {
            return response()->json(['type' => 1]);
        }

        // type 3: MESSAGE_COMPONENT
        if ($type === 3) {
            $member = $request->input('member');
            $discordId = $member['user']['id'] ?? null;
            $customId = $request->input('data.custom_id');

            if (!$discordId) {
                return response()->json(['type' => 4, 'data' => ['content' => 'Error: Could not identify Discord user.']], 200);
            }

            $user = User::where('discord_id', $discordId)->first();
            if (!$user) {
                return response()->json([
                    'type' => 4,
                    'data' => [
                        'content' => 'Your Discord account is not linked to StaticHub. Please link it in your profile settings.',
                        'flags' => 64 // Ephemeral
                    ]
                ], 200);
            }

            // Parse customId: rsvp_select_{eventId}
            if (str_starts_with($customId, 'rsvp_select_')) {
                $eventId = str_replace('rsvp_select_', '', $customId);
                $event = RaidEvent::find($eventId);

                if (!$event) {
                    return response()->json(['type' => 4, 'data' => ['content' => 'Raid event not found.']], 200);
                }

                $status = $request->input('data.values.0');

                // For Discord RSVP, we need to find the user's main character for this static
                $character = $user->characters->filter(function ($char) use ($event) {
                    foreach ($char->statics as $s) {
                        if ($s->id == $event->static_id && $s->pivot->role === 'main') {
                            return true;
                        }
                    }
                    return false;
                })->first();

                if (!$character) {
                    $character = $user->characters->first();
                }

                if (!$character) {
                    return response()->json(['type' => 4, 'data' => ['content' => 'You have no characters linked to this static.']], 200);
                }

                $this->attendanceService->updateAttendance($event, $character, $status);

                $newPayload = $this->discordMessageService->buildRaidMessage($event);

                return response()->json([
                    'type' => 7, // UPDATE_MESSAGE
                    'data' => $newPayload
                ]);
            }

            // Handle other buttons (Comment, Switch Character) as placeholders or ephemeral messages
            if (str_starts_with($customId, 'rsvp_comment_')) {
                return response()->json([
                    'type' => 4,
                    'data' => [
                        'content' => 'Comments via Discord are coming soon! Please use the web interface for now.',
                        'flags' => 64
                    ]
                ]);
            }

            if (str_starts_with($customId, 'rsvp_switch_')) {
                return response()->json([
                    'type' => 4,
                    'data' => [
                        'content' => 'Character switching via Discord is coming soon! Please use the web interface for now.',
                        'flags' => 64
                    ]
                ]);
            }
        }

        return response()->json(['type' => 4, 'data' => ['content' => 'Interaction type not supported.']], 200);
    }
}
