<?php

namespace App\Services;

use App\Models\RaidEvent;
use App\Models\User;
use App\Models\Character;
use Illuminate\Http\Request;

class DiscordInteractionService
{
    public function __construct(
        private readonly RaidAttendanceService $attendanceService,
        private readonly DiscordMessageService $discordMessageService
    ) {}

    public function handle(Request $request): array
    {
        $type = $request->input('type');

        // type 1: PING
        if ($type === 1) {
            return ['type' => 1];
        }

        // type 3: MESSAGE_COMPONENT
        if ($type === 3) {
            return $this->handleMessageComponent($request);
        }

        return ['type' => 4, 'data' => ['content' => 'Interaction type not supported.']];
    }

    private function handleMessageComponent(Request $request): array
    {
        $member = $request->input('member');
        $discordId = $member['user']['id'] ?? null;
        $customId = $request->input('data.custom_id');

        if (!$discordId) {
            return ['type' => 4, 'data' => ['content' => 'Error: Could not identify Discord user.']];
        }

        $user = User::where('discord_id', $discordId)->first();
        if (!$user) {
            return [
                'type' => 4,
                'data' => [
                    'content' => 'Your Discord account is not linked to StaticHub. Please link it in your profile settings.',
                    'flags' => 64 // Ephemeral
                ]
            ];
        }

        if (str_starts_with($customId, 'rsvp_select_')) {
            return $this->handleRsvpSelect($request, $user, $customId);
        }

        if (str_starts_with($customId, 'rsvp_comment_')) {
            return [
                'type' => 4,
                'data' => [
                    'content' => 'Comments via Discord are coming soon! Please use the web interface for now.',
                    'flags' => 64
                ]
            ];
        }

        if (str_starts_with($customId, 'rsvp_switch_')) {
            return [
                'type' => 4,
                'data' => [
                    'content' => 'Character switching via Discord is coming soon! Please use the web interface for now.',
                    'flags' => 64
                ]
            ];
        }

        return ['type' => 4, 'data' => ['content' => 'Unknown interaction component.']];
    }

    private function handleRsvpSelect(Request $request, User $user, string $customId): array
    {
        $eventId = str_replace('rsvp_select_', '', $customId);
        $event = RaidEvent::find($eventId);

        if (!$event) {
            return ['type' => 4, 'data' => ['content' => 'Raid event not found.']];
        }

        $status = $request->input('data.values.0');
        $character = $this->findMainCharacterForStatic($user, $event->static_id);

        if (!$character) {
            return ['type' => 4, 'data' => ['content' => 'You have no characters linked to this static.']];
        }

        $this->attendanceService->updateAttendance($event, $character, $status);

        return [
            'type' => 7, // UPDATE_MESSAGE
            'data' => $this->discordMessageService->buildRaidMessage($event)
        ];
    }

    private function findMainCharacterForStatic(User $user, int $staticId): ?Character
    {
        // For Discord RSVP, we need to find the user's main character for this static
        return Character::query()->findMainInStatic($user->id, $staticId)
            ?? $user->characters()->first();
    }
}
