<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Jobs\SyncCharacterItemLevelJob;
use App\Models\Character;
use App\Models\Realm;
use App\Services\BlizzardApiService;

class CharacterSyncService
{
    public function __construct(
        private readonly BlizzardApiService $blizzardApiService
    ) {
    }

    /**
     * Orchestrates fetching characters from Blizzard API and storing them in the database.
     */
    public function syncUserCharacters(string $token, int $userId): void
    {
        $apiCharacters = $this->blizzardApiService->getUserCharacters($token);

        foreach ($apiCharacters as $apiData) {
            $this->processSingleCharacter($apiData, $userId);
        }
    }

    /**
     * Processes a single character from Blizzard API data.
     */
    private function processSingleCharacter(array $apiData, int $userId): void
    {
        $realm = $this->resolveRealm($apiData);
        $avatarUrl = $this->fetchAvatarUrl($apiData);
        $character = $this->upsertCharacter($apiData, $userId, $realm->id, $avatarUrl);

        $this->dispatchSyncJob($character);
    }

    /**
     * Resolves the Realm model for the character.
     */
    private function resolveRealm(array $apiData): Realm
    {
        return Realm::firstOrCreate(
            ['slug' => $apiData['realm_slug']],
            ['name' => $apiData['realm'], 'region' => 'eu']
        );
    }

    /**
     * Fetches the avatar URL for the character.
     */
    private function fetchAvatarUrl(array $apiData): ?string
    {
        return $this->blizzardApiService->getCharacterAvatar($apiData['realm_slug'], $apiData['name']);
    }

    /**
     * Updates or creates the Character model.
     */
    private function upsertCharacter(array $apiData, int $userId, int $realmId, ?string $avatarUrl): Character
    {
        return Character::query()->syncFromBlizzard($apiData, $userId, $realmId, $avatarUrl);
    }

    /**
     * Dispatches the sync job for the character.
     */
    private function dispatchSyncJob(Character $character): void
    {
        SyncCharacterItemLevelJob::dispatch($character);
    }
}
