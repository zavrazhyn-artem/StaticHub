<?php

namespace App\Services;

use App\Models\Character;
use App\Models\User;
use App\Models\Realm;
use App\Jobs\SyncCharacterItemLevelJob;
use Illuminate\Support\Facades\Auth;

class CharacterSyncService
{
    protected BlizzardApiService $blizzardApiService;

    public function __construct(BlizzardApiService $blizzardApiService)
    {
        $this->blizzardApiService = $blizzardApiService;
    }

    /**
     * Orchestrates fetching characters from Blizzard API and storing them in the database.
     */
    public function syncUserCharacters(string $token, int $userId): void
    {
        $apiCharacters = $this->blizzardApiService->getUserCharacters($token);

        foreach ($apiCharacters as $apiData) {
            $realm = Realm::firstOrCreate(
                ['slug' => $apiData['realm_slug']],
                ['name' => $apiData['realm'], 'region' => 'eu']
            );

            $avatarUrl = $this->blizzardApiService->getCharacterAvatar($apiData['realm_slug'], $apiData['name']);

            $character = Character::syncFromBlizzard($apiData, $userId, $realm->id, $avatarUrl);

            SyncCharacterItemLevelJob::dispatch($character);
        }
    }
}
