<?php

namespace App\Services;

use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Support\Str;

class OnboardingService
{
    /**
     * Get data for the onboarding index page.
     *
     * @param User $user
     * @return array
     */
    public function getOnboardingData(User $user): array
    {
        $userCharacters = $user->characters;

        $isGuildMaster = false;
        $guildName = null;

        $gmCharacter = $userCharacters->first(function ($character) {
             return property_exists($character, 'guild_rank') && $character->guild_rank === 0;
        });

        if ($gmCharacter) {
            $isGuildMaster = true;
            $guildName = $gmCharacter->guild_name ?? 'Unknown Guild';
        }

        return [
            'isGuildMaster' => $isGuildMaster,
            'guildName' => $guildName,
            'userCharacters' => $userCharacters,
        ];
    }

    /**
     * Create a new static group and attach the owner.
     *
     * @param array $data
     * @param int $ownerId
     * @return StaticGroup
     */
    public function createStatic(array $data, int $ownerId): StaticGroup
    {
        $static = StaticGroup::create([
            'name' => $data['name'],
            'region' => $data['region'],
            'owner_id' => $ownerId,
            'invite_token' => Str::random(12),
            'slug' => Str::slug($data['name']),
        ]);

        // Attach user to static as owner
        $static->members()->attach($ownerId, ['role' => 'owner']);

        return $static;
    }
}
