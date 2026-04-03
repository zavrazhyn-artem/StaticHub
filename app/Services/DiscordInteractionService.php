<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\Discord\ProcessRsvpInteractionJob;
use App\Jobs\Discord\SwapRsvpCharacterJob;

use App\Helpers\DiscordConstants;
use App\Models\Character;
use App\Models\RaidEvent;
use App\Models\User;

class DiscordInteractionService
{
    /**
     * Main Orchestrator for Discord Interactions.
     */
    public function handle(array $payload): array
    {
        $type = (int) ($payload['type'] ?? 0);

        return match ($type) {
            DiscordConstants::TYPE_PING => ['type' => DiscordConstants::RESPONSE_PONG],
            DiscordConstants::TYPE_MESSAGE_COMPONENT => $this->handleMessageComponent($payload),
            default => [
                'type' => DiscordConstants::RESPONSE_CHANNEL_MESSAGE,
                'data' => ['content' => 'Interaction type not supported.'],
            ],
        };
    }

    /**
     * Task: Handle message component interactions (buttons, selects).
     */
    private function handleMessageComponent(array $payload): array
    {
        $user = $this->resolveDiscordUser($payload['member'] ?? null);
        $customId = $payload['data']['custom_id'] ?? '';

        if (!$user) {
            return [
                'type' => DiscordConstants::RESPONSE_CHANNEL_MESSAGE,
                'data' => [
                    'content' => 'Your Discord account is not linked to StaticHub. Please link it in your profile settings.',
                    'flags' => DiscordConstants::FLAG_EPHEMERAL,
                ],
            ];
        }

        return $this->processComponentAction($customId, $payload, $user);
    }

    /**
     * Task: Resolve Discord member data to a User model.
     */
    private function resolveDiscordUser(?array $memberData): ?User
    {
        $discordId = $memberData['user']['id'] ?? null;

        if (!$discordId) {
            return null;
        }

        return User::where('discord_id', $discordId)->first();
    }

    /**
     * Task: Process the specific component action.
     */
    private function processComponentAction(string $customId, array $payload, User $user): array
    {
        if (str_starts_with($customId, 'rsvp_select_')) {
            return $this->processRsvpAction($customId, $payload['data']['values'] ?? [], $user);
        }

        if (str_starts_with($customId, 'rsvp_switch_')) {
            return $this->processSwitchCharacterAction($customId, $user);
        }

        if (str_starts_with($customId, 'rsvp_confirm_char_')) {
            return $this->processConfirmCharacterAction($customId, $payload, $user);
        }

        if (str_starts_with($customId, 'rsvp_comment_')) {
            return [
                'type' => DiscordConstants::RESPONSE_CHANNEL_MESSAGE,
                'data' => [
                    'content' => 'This feature is coming soon to Discord! Please use the web interface for now.',
                    'flags' => DiscordConstants::FLAG_EPHEMERAL,
                ],
            ];
        }

        return [
            'type' => DiscordConstants::RESPONSE_CHANNEL_MESSAGE,
            'data' => ['content' => 'Unknown interaction component.'],
        ];
    }

    /**
     * Task: Process the "Switch Character" button click.
     */
    private function processSwitchCharacterAction(string $customId, User $user): array
    {
        $eventId = (int) str_replace('rsvp_switch_', '', $customId);
        $event = RaidEvent::find($eventId);

        if (!$event) {
            return [
                'type' => DiscordConstants::RESPONSE_CHANNEL_MESSAGE,
                'data' => [
                    'content' => 'Raid event not found.',
                    'flags' => DiscordConstants::FLAG_EPHEMERAL,
                ],
            ];
        }

        $characters = Character::query()
            ->where('user_id', $user->id)
            ->whereHas('statics', fn($q) => $q->where('statics.id', $event->static_id))
            ->get();

        if ($characters->count() <= 1) {
            return [
                'type' => DiscordConstants::RESPONSE_CHANNEL_MESSAGE,
                'data' => [
                    'content' => 'You only have one character linked to this static.',
                    'flags' => DiscordConstants::FLAG_EPHEMERAL,
                ],
            ];
        }

        $options = $characters->map(fn($char) => [
            'label' => "{$char->name} ({$char->playable_class})",
            'value' => (string) $char->id,
        ])->toArray();

        return [
            'type' => DiscordConstants::RESPONSE_CHANNEL_MESSAGE,
            'data' => [
                'content' => 'Select the character you want to use for this raid:',
                'flags' => DiscordConstants::FLAG_EPHEMERAL,
                'components' => [
                    [
                        'type' => 1,
                        'components' => [
                            [
                                'type' => 3,
                                'custom_id' => "rsvp_confirm_char_{$eventId}",
                                'options' => $options,
                                'placeholder' => 'Choose a character...',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Task: Process the character selection from the ephemeral menu.
     */
    private function processConfirmCharacterAction(string $customId, array $payload, User $user): array
    {
        $eventId = (int) str_replace('rsvp_confirm_char_', '', $customId);
        $characterId = (int) ($payload['data']['values'][0] ?? 0);

        if ($characterId > 0) {
            SwapRsvpCharacterJob::dispatch($eventId, $user->id, $characterId);
        }

        return [
            'type' => DiscordConstants::RESPONSE_UPDATE_MESSAGE,
            'data' => [
                'content' => '✅ Character successfully updated for this raid! (Updating main roster...)',
                'components' => [],
            ],
        ];
    }

    /**
     * Task: Process RSVP selection and update attendance.
     */
    private function processRsvpAction(string $customId, array $values, User $user): array
    {
        $eventId = (int) str_replace('rsvp_select_', '', $customId);
        $event = RaidEvent::find($eventId);

        if (!$event) {
            return [
                'type' => DiscordConstants::RESPONSE_CHANNEL_MESSAGE,
                'data' => ['content' => 'Raid event not found.'],
            ];
        }

        $status = $values[0] ?? null;
        $character = $user->getMainCharacterForStatic($event->static_id);

        if (!$character) {
            return [
                'type' => DiscordConstants::RESPONSE_CHANNEL_MESSAGE,
                'data' => ['content' => 'You have no characters linked to this static.'],
            ];
        }

        ProcessRsvpInteractionJob::dispatch($event->id, $character->id, $status);

        return [
            'type' => DiscordConstants::RESPONSE_DEFERRED_UPDATE_MESSAGE,
        ];
    }
}
