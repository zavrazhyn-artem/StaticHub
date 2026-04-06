<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\Realm;
use App\Models\StaticGroup;
use App\Services\Blizzard\BlizzardGuildApiService;
use App\Services\StaticGroup\ConsumableService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaticService
{
    /**
     * Create a new StaticService instance.
     *
     * @param BlizzardGuildApiService $blizzardApi
     * @param ConsumableService $consumableService
     */
    public function __construct(
        protected BlizzardGuildApiService $blizzardApi,
        protected ConsumableService $consumableService
    ) {
    }

    /**
     * Fetch the default static group for a user.
     *
     * @param int $userId
     * @return StaticGroup
     * @throws \RuntimeException
     */
    public function getDefaultStaticForUser(int $userId): StaticGroup
    {
        $static = StaticGroup::query()->firstForUser($userId);

        if (!$static) {
            throw new \RuntimeException('No static group found.');
        }

        return $static;
    }

    /**
     * Build the setup payload for the static setup page.
     *
     * @param int $userId
     * @param string|null $token
     * @return array
     */
    public function buildSetupPayload(int $userId, ?string $token): array
    {
        $statics = StaticGroup::whereUserIsMember($userId)->get();

        $guilds = [];
        if ($token) {
            $guilds = $this->blizzardApi->getUserGuilds($token);
        }

        $realms = Realm::orderedByName()->get();

        return compact('statics', 'guilds', 'realms');
    }

    /**
     * Get an invite link for a static group.
     *
     * @param StaticGroup $static
     * @return string
     */
    public function getInviteLink(StaticGroup $static): string
    {
        $token = $static->refreshInviteTokenIfNeeded();

        return route('statics.join', $token);
    }

    /**
     * Execute the creation of a new static group.
     *
     * @param array $data
     * @param int $userId
     * @return StaticGroup
     * @throws ModelNotFoundException
     */
    public function executeCreation(array $data, int $userId): StaticGroup
    {
        $realm = Realm::findBySlug($data['realm_slug']);

        if (!$realm) {
            throw new ModelNotFoundException();
        }

        return $this->createStaticGroup(
            $data['name'],
            $realm->name,
            $realm->slug,
            $data['region'],
            $userId
        );
    }

    /**
     * Execute the import of a guild as a static group.
     *
     * @param array $data
     * @param int $userId
     * @return StaticGroup
     */
    public function executeGuildImport(array $data, int $userId): StaticGroup
    {
        return $this->createStaticGroup(
            $data['name'],
            $data['realm'],
            $data['realm_slug'],
            'eu',
            $userId
        );
    }

    /**
     * Create a new static group and assign its owner.
     *
     * @param string $name
     * @param string $realmName
     * @param string $realmSlug
     * @param string $region
     * @param int $ownerId
     * @return StaticGroup
     */
    public function createStaticGroup(string $name, string $realmName, string $realmSlug, string $region, int $ownerId): StaticGroup
    {
        return DB::transaction(function () use ($name, $realmName, $realmSlug, $region, $ownerId) {
            $static = StaticGroup::create([
                'name' => $name,
                'slug' => Str::slug($name . '-' . $realmSlug),
                'server' => $realmName,
                'region' => $region,
                'owner_id' => $ownerId,
            ]);

            $calculatedCost = $this->consumableService->buildConsumablesPayload($static)['grand_total_weekly_cost'] ?? 0;
            $costPerPlayer = $calculatedCost / 20;
            // Round up to nearest 1000 gold (10 000 000 copper)
            $roundedTax = (int) (ceil($costPerPlayer / 10000000) * 10000000);
            $static->update(['weekly_tax_per_player' => $roundedTax]);

            $static->assignOwner($ownerId);

            return $static;
        });
    }
}
