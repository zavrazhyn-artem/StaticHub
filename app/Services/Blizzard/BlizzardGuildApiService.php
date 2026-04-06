<?php

declare(strict_types=1);

namespace App\Services\Blizzard;

use Illuminate\Support\Facades\Http;

class BlizzardGuildApiService
{
    public function __construct(
        private readonly BlizzardAuthService $authService,
    ) {}

    /**
     * Fetch user characters and guilds they lead.
     */
    public function getUserGuilds(string $userToken): array
    {
        $data = $this->fetchUserProfile($userToken);
        if (empty($data)) {
            return [];
        }

        return $this->parseGuildsFromAccounts($data['wow_accounts'] ?? []);
    }

    private function fetchUserProfile(string $userToken): array
    {
        $region = $this->authService->getRegion();

        $response = Http::withToken($userToken)
            ->get("https://{$region}.api.blizzard.com/profile/user/wow");

        return $response->successful() ? $response->json() : [];
    }

    private function parseGuildsFromAccounts(array $accounts): array
    {
        $guilds = [];

        foreach ($accounts as $account) {
            $characters = $account['characters'] ?? [];
            foreach ($characters as $character) {
                if (isset($character['guild'])) {
                    $guildKey = "{$character['guild']['name']}-{$character['realm']['slug']}";
                    if (!isset($guilds[$guildKey])) {
                        $guilds[$guildKey] = [
                            'name' => $character['guild']['name'],
                            'realm' => $character['realm']['name'],
                            'realm_slug' => $character['realm']['slug'],
                            'character_name' => $character['name'],
                        ];
                    }
                }
            }
        }

        return array_values($guilds);
    }

    /**
     * Check if a character is the leader of a guild.
     */
    public function isGuildLeader(string $userToken, string $realmSlug, string $guildSlug): bool
    {
        $roster = $this->fetchGuildRoster($userToken, $realmSlug, $guildSlug);

        return $this->hasLeaderInRoster($roster);
    }

    private function fetchGuildRoster(string $userToken, string $realmSlug, string $guildSlug): array
    {
        $region = $this->authService->getRegion();

        $response = Http::withToken($userToken)
            ->withHeaders(['Battlenet-Namespace' => "profile-{$region}"])
            ->get("https://{$region}.api.blizzard.com/data/wow/guild/{$realmSlug}/{$guildSlug}/roster");

        return $response->successful() ? $response->json('members', []) : [];
    }

    private function hasLeaderInRoster(array $members): bool
    {
        foreach ($members as $member) {
            // Rank 0 is usually Guild Master
            if (($member['rank'] ?? -1) === 0) {
                return true;
            }
        }

        return false;
    }
}
