<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Jobs\Character\SyncCharacterItemLevelJob;
use App\Models\Character;
use App\Models\Realm;
use App\Models\User;
use App\Services\Blizzard\BlizzardCharacterApiService;
use App\Services\StaticGroup\RosterService;

class CharacterSyncService
{
    public function __construct(
        private readonly BlizzardCharacterApiService  $blizzardApiService,
        private readonly RosterService       $rosterService,
        private readonly RawDataSyncService  $rawDataSyncService,
    ) {}

    /**
     * Orchestrates fetching characters from Blizzard API and storing them in the database.
     */
    public function syncUserCharacters(string $token, int $userId): void
    {
        $apiCharacters = $this->blizzardApiService->getUserCharacters($token);

        foreach ($apiCharacters as $apiData) {
            $this->processSingleCharacter($apiData, $userId);
        }

        $this->autoSetSpecsForUserStatics($userId);
    }

    /**
     * Auto-set main specs for all user's characters across all their statics.
     */
    public function autoSetSpecsForUserStatics(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $staticIds = $user->statics()->pluck('statics.id');
        if ($staticIds->isEmpty()) {
            return;
        }

        $characters = Character::query()
            ->belongingTo($userId)
            ->whereNotNull('active_spec')
            ->get();

        foreach ($staticIds as $staticId) {
            foreach ($characters as $character) {
                $this->rosterService->autoSetMainSpecIfMissing($character, (int) $staticId);
            }
        }
    }

    /**
     * Processes a single character from Blizzard API data.
     */
    private function processSingleCharacter(array $apiData, int $userId): void
    {
        $realm     = $this->resolveRealm($apiData);
        $avatarUrl = $this->fetchAvatarUrl($apiData);
        $character = $this->upsertCharacter($apiData, $userId, $realm->id, $avatarUrl);

        // Fetch the character's profile summary to get active_spec and ilvl.
        // This is done synchronously so that the spec assignment below works
        // immediately — we cannot rely on the queued job for this.
        $this->syncProfileAndSpec($character);

        $this->dispatchSyncJob($character);
    }

    /**
     * Fetches the profile summary for the character, updates active_spec /
     * equipped_item_level, and auto-sets the main spec for every static the
     * character belongs to (skipped if specs are already configured).
     */
    public function syncProfileAndSpec(Character $character): void
    {
        $profileData = $this->blizzardApiService->getCharacterProfileSummary(
            $character->realm->slug,
            $character->name,
        );

        if ($profileData === null) {
            return;
        }

        $activeSpec = is_array($profileData['active_spec'])
            ? ($profileData['active_spec']['name'] ?? null)
            : ($profileData['active_spec'] ?? null);

        // active_specialization is the field name in some API versions
        if ($activeSpec === null) {
            $activeSpec = is_array($profileData['active_specialization'])
                ? ($profileData['active_specialization']['name'] ?? null)
                : ($profileData['active_specialization'] ?? null);
        }

        $character->update([
            'equipped_item_level' => $profileData['equipped_item_level'] ?? $character->equipped_item_level,
            'active_spec'         => $activeSpec ?? $character->active_spec,
        ]);

        $character->refresh();

        // Auto-set main spec for each static this character belongs to.
        $staticIds = $character->statics()->pluck('statics.id');
        foreach ($staticIds as $staticId) {
            $this->rosterService->autoSetMainSpecIfMissing($character, (int) $staticId);
        }
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
     * Dispatches the full sync job for the character (ilvl, compiled data, etc.).
     */
    private function dispatchSyncJob(Character $character): void
    {
        SyncCharacterItemLevelJob::dispatch($character);
    }

    /**
     * Delegate raw data sync to RawDataSyncService.
     */
    public function syncRawData(Character $character, string $service = 'all'): void
    {
        $this->rawDataSyncService->syncRawData($character, $service);
    }
}
