<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\Realm;
use App\Models\StaticGroup;
use App\Services\BlizzardApiService;
use App\Tasks\StaticGroup\CreateStaticGroupTask;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StaticService
{
    /**
     * Create a new StaticService instance.
     *
     * @param BlizzardApiService $blizzardApi
     * @param CreateStaticGroupTask $createStaticGroupTask
     */
    public function __construct(
        protected BlizzardApiService $blizzardApi,
        protected CreateStaticGroupTask $createStaticGroupTask
    ) {
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

        return $this->createStaticGroupTask->run(
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
        return $this->createStaticGroupTask->run(
            $data['name'],
            $data['realm'],
            $data['realm_slug'],
            'eu',
            $userId
        );
    }
}
