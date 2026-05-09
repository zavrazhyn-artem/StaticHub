<?php

declare(strict_types=1);

namespace App\Services\Loot;

use App\Models\Character;
use App\Models\LootDistribution;

/**
 * Re-resolves `recipient_character_id` for historic loot rows after a
 * character joins a static. Awards captured before the character existed
 * in the roster (or before the user assigned them) live with
 * `recipient_character_id = NULL` and only `recipient_name` to identify
 * the player. When the character finally lands in the roster we walk
 * those orphan rows once and stamp the FK.
 *
 * Triggered from `RosterService::assignCharacterToStatic`. Reads the
 * orphans for this static (small set), canonicalises recipient_name
 * per-row in PHP, and bulk-updates matched ids — sidesteps trying to
 * shoe-horn the canonicalisation rule into raw SQL.
 */
final class LootHistoryBackfillService
{
    public function backfillForCharacter(Character $character, int $staticId): int
    {
        $character->loadMissing('realm');
        $realm = $character->realm;
        if (! $realm) return 0;

        $target = $this->canonKey($character->name, $realm->slug);

        $orphanIds = LootDistribution::query()
            ->forStatic($staticId)
            ->whereNull('recipient_character_id')
            ->whereNotNull('recipient_name')
            ->pluck('recipient_name', 'id');

        $matchedIds = [];
        foreach ($orphanIds as $id => $rawName) {
            [$rowName, $rowRealm] = $this->splitCharacterKey((string) $rawName);
            if ($rowName === '' || $rowRealm === '') continue;
            if ($this->canonKey($rowName, $rowRealm) === $target) {
                $matchedIds[] = $id;
            }
        }

        if (empty($matchedIds)) return 0;

        return LootDistribution::query()
            ->whereIn('id', $matchedIds)
            ->update(['recipient_character_id' => $character->id]);
    }

    private function canonKey(string $name, string $realm): string
    {
        $canonName  = mb_strtolower($name, 'UTF-8');
        $canonRealm = mb_strtolower(preg_replace('/[\s\-_\'\.]+/u', '', $realm) ?? '', 'UTF-8');
        return $canonName . '|' . $canonRealm;
    }

    /** "Name-Realm-With-Dashes" → ['Name', 'Realm-With-Dashes']. */
    private function splitCharacterKey(string $key): array
    {
        $pos = strpos($key, '-');
        if ($pos === false) return ['', ''];
        return [substr($key, 0, $pos), substr($key, $pos + 1)];
    }
}
