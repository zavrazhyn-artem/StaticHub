<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Jobs\Character\FetchBnetRawDataJob;
use App\Jobs\Character\FetchRioRawDataJob;
use App\Jobs\Character\SyncCharacterItemLevelJob;
use App\Models\Character;
use App\Models\CharacterStaticSpec;
use App\Models\Realm;
use App\Models\Specialization;
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
     * Uses batch HTTP calls to fetch avatars and profiles concurrently.
     */
    public function syncUserCharacters(string $token, int $userId): void
    {
        $apiCharacters = $this->blizzardApiService->getUserCharacters($token);

        if (empty($apiCharacters)) {
            return;
        }

        // Batch fetch all avatars concurrently
        $avatars = $this->blizzardApiService->fetchAvatarsBatch($apiCharacters);

        // Resolve realms in batch — cache by slug to avoid repeated queries
        $realmCache = [];
        foreach ($apiCharacters as $apiData) {
            $slug = $apiData['realm_slug'];
            if (!isset($realmCache[$slug])) {
                $realmCache[$slug] = $this->resolveRealm($apiData);
            }
        }

        // Upsert all characters with their avatars
        $characters = [];
        foreach ($apiCharacters as $idx => $apiData) {
            $realm = $realmCache[$apiData['realm_slug']];
            $characters[$idx] = $this->upsertCharacter($apiData, $userId, $realm->id, $avatars[$idx] ?? null);
        }

        // Batch fetch all profile summaries concurrently
        $profiles = $this->blizzardApiService->fetchProfilesBatch($apiCharacters);

        // Build batch updates from profiles
        foreach ($characters as $idx => $character) {
            $profileData = $profiles[$idx] ?? null;
            if ($profileData === null) {
                continue;
            }

            $activeSpec = is_array($profileData['active_spec'] ?? null)
                ? ($profileData['active_spec']['name'] ?? null)
                : ($profileData['active_spec'] ?? null);

            if ($activeSpec === null) {
                $activeSpec = is_array($profileData['active_specialization'] ?? null)
                    ? ($profileData['active_specialization']['name'] ?? null)
                    : ($profileData['active_specialization'] ?? null);
            }

            $character->equipped_item_level = $profileData['equipped_item_level'] ?? $character->equipped_item_level;
            $character->active_spec = $activeSpec ?? $character->active_spec;
        }

        // Bulk update all characters in one query per batch
        Character::upsert(
            collect($characters)->map(fn (Character $c) => [
                'id' => $c->id,
                'equipped_item_level' => $c->equipped_item_level,
                'active_spec' => $c->active_spec,
                'updated_at' => now(),
            ])->values()->all(),
            ['id'],
            ['equipped_item_level', 'active_spec', 'updated_at']
        );

        // Dispatch async sync jobs for all characters
        foreach ($characters as $character) {
            $this->dispatchSyncJob($character);
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

        $staticIds = $user->statics()->pluck('statics.id')->all();
        if (empty($staticIds)) {
            return;
        }

        $characters = Character::query()
            ->belongingTo($userId)
            ->whereNotNull('active_spec')
            ->get();

        if ($characters->isEmpty()) {
            return;
        }

        $characterIds = $characters->pluck('id')->all();

        // Load all existing spec assignments in one query
        $existingSpecs = CharacterStaticSpec::whereIn('character_id', $characterIds)
            ->whereIn('static_id', $staticIds)
            ->get()
            ->map(fn ($s) => "{$s->character_id}|{$s->static_id}")
            ->flip();

        // Load all specializations in one query (small table)
        $specLookup = Specialization::all()
            ->keyBy(fn ($s) => "{$s->name}|{$s->class_name}");

        $inserts = [];
        $now = now();

        foreach ($staticIds as $staticId) {
            foreach ($characters as $character) {
                $key = "{$character->id}|{$staticId}";
                if (isset($existingSpecs[$key])) {
                    continue;
                }

                if (!$character->active_spec || !$character->playable_class) {
                    continue;
                }

                $spec = $specLookup["{$character->active_spec}|{$character->playable_class}"] ?? null;
                if (!$spec) {
                    continue;
                }

                $inserts[] = [
                    'character_id' => $character->id,
                    'static_id'    => $staticId,
                    'spec_id'      => $spec->id,
                    'is_main'      => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }

        if (!empty($inserts)) {
            CharacterStaticSpec::insert($inserts);
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
        FetchBnetRawDataJob::dispatch($character);
        FetchRioRawDataJob::dispatch($character);
    }

    /**
     * Delegate raw data sync to RawDataSyncService.
     */
    public function syncRawData(Character $character, string $service = 'all'): void
    {
        $this->rawDataSyncService->syncRawData($character, $service);
    }
}
