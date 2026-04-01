<?php

namespace App\Services;

use App\Models\Realm;
use App\Models\StaticGroup;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class StaticService
{
    protected BlizzardApiService $blizzardApi;

    public function __construct(BlizzardApiService $blizzardApi)
    {
        $this->blizzardApi = $blizzardApi;
    }

    /**
     * Get data for the static setup page.
     */
    public function getSetupData(int $userId, ?string $token): array
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
     * Generate an invite link for a static group.
     */
    public function generateInvite(StaticGroup $static): string
    {
        if ($static->invite_token && $static->invite_until && $static->invite_until->isFuture()) {
            return route('statics.join', $static->invite_token);
        }

        $static->update([
            'invite_token' => Str::random(12),
            'invite_until' => now()->addDay(),
        ]);

        return route('statics.join', $static->invite_token);
    }

    /**
     * Create a new static group.
     */
    public function createStatic(array $data, int $userId): StaticGroup
    {
        $realm = Realm::findBySlug($data['realm_slug']);

        if (!$realm) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }

        return DB::transaction(function () use ($data, $realm, $userId) {
            $static = StaticGroup::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name'] . '-' . $realm->slug),
                'server' => $realm->name,
                'region' => $data['region'],
                'owner_id' => $userId,
            ]);

            $static->members()->attach($userId, ['role' => 'owner']);

            return $static;
        });
    }

    /**
     * Import a guild as a static group.
     */
    public function importGuild(array $data, int $userId): StaticGroup
    {
        return DB::transaction(function () use ($data, $userId) {
            $static = StaticGroup::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name'] . '-' . $data['realm_slug']),
                'server' => $data['realm'],
                'region' => 'eu', // Defaulting to EU as per project context
                'owner_id' => $userId,
            ]);

            $static->members()->attach($userId, ['role' => 'owner']);

            return $static;
        });
    }
}
