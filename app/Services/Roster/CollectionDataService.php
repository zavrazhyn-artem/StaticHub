<?php

declare(strict_types=1);

namespace App\Services\Roster;

/**
 * Handles collection data: pets, PvP brackets, and reputation/renown.
 */
final class CollectionDataService
{
    /** PvP season number. */
    private readonly int $pvpSeason;

    /** Reputation faction IDs. */
    private readonly array $reputations;

    public function __construct()
    {
        $this->pvpSeason   = (int) config('wow_season.pvp_season', 0);
        $this->reputations = config('wow_season.reputations', []);
    }

    // =========================================================================
    // PETS
    // =========================================================================

    public function resolveUniquePets(array $pets): int
    {
        $seen = [];
        foreach ($pets['pets'] ?? [] as $pet) {
            if (!is_array($pet)) {
                continue;
            }
            $speciesId = $pet['species']['id'] ?? null;
            if ($speciesId !== null) {
                $seen[$speciesId] = true;
            }
        }
        return count($seen);
    }

    public function resolveLvl25Pets(array $pets): int
    {
        $count = 0;
        $seen  = [];
        foreach ($pets['pets'] ?? [] as $pet) {
            if (!is_array($pet)) {
                continue;
            }
            $speciesId = $pet['species']['id'] ?? null;
            if ($speciesId !== null && !isset($seen[$speciesId])) {
                $seen[$speciesId] = true;
                if (($pet['level'] ?? 0) === 25) {
                    $count++;
                }
            }
        }
        return $count;
    }

    // =========================================================================
    // PVP
    // =========================================================================

    public function resolvePvpBrackets(array $pvpSum): array
    {
        $brackets = [];
        foreach (['2v2', '3v3', 'rbg', 'shuffle'] as $bracket) {
            $brackets[$bracket] = [
                'rating'        => 0,
                'season_played' => 0,
                'week_played'   => 0,
            ];
        }
        return $brackets;
    }

    // =========================================================================
    // REPUTATION
    // =========================================================================

    public function resolveRenown(array $reps): array
    {
        $result = [];

        foreach ($this->reputations as $factionId => $name) {
            $level = 0;
            foreach ($reps['reputations'] ?? [] as $rep) {
                if (($rep['faction']['id'] ?? 0) === $factionId) {
                    $level = (int) ($rep['standing']['renown_level'] ?? 0);
                    break;
                }
            }
            $result[$name] = $level;
        }

        return $result;
    }
}
