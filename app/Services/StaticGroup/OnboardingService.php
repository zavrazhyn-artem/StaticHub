<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Support\Str;

class OnboardingService
{
    /**
     * Build the onboarding payload for the given user.
     */
    public function buildOnboardingPayload(User $user): array
    {
        return array_merge([
            'userCharacters' => $user->characters,
        ], $this->extractGuildInfo($user));
    }

    /**
     * Execute the creation of a new static group.
     */
    public function executeStaticCreation(array $data, int $ownerId): StaticGroup
    {
        $static = $this->createStaticRecord($data, $ownerId);

        $this->setupStaticOwner($static, $ownerId);

        return $static;
    }

    /**
     * Extract guild information for the user.
     */
    private function extractGuildInfo(User $user): array
    {
        $gmCharacter = $user->getGuildMasterCharacter();

        return [
            'isGuildMaster' => $gmCharacter !== null,
            'guildName' => $gmCharacter ? ($gmCharacter->guild_name ?? 'Unknown Guild') : null,
        ];
    }

    /**
     * Create the static group record in the database.
     */
    private function createStaticRecord(array $data, int $ownerId): StaticGroup
    {
        return StaticGroup::create([
            'name' => $data['name'],
            'region' => $data['region'],
            'owner_id' => $ownerId,
            'invite_token' => Str::random(12),
            'slug' => Str::slug($data['name']),
        ]);
    }

    /**
     * Setup the owner for the newly created static group.
     */
    private function setupStaticOwner(StaticGroup $static, int $ownerId): void
    {
        $static->assignOwner($ownerId);
    }
}
