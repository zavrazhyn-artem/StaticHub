<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds the season_items catalog with the full Midnight Season 1 raid + M+ +
 * catalyst loot from a one-time JSON dump fetched from liquidarmory.com.
 *
 * The dump lives at database/seeders/data/midnight-s1-loot.json. Re-running
 * this seeder is idempotent (upserts on item id, replaces class allowances)
 * and safe against accidental re-runs.
 *
 * The catalog is decoupled from the universal `items` table — that table is
 * shared with auctions/recipes/legacy gear lists, so season metadata lives
 * here independently and can be wiped and re-seeded each season.
 */
class MidnightS1LootSeeder extends Seeder
{
    private const SEASON_SLUG = 'mid-s1';

    /** Raid name → Blizzard journal-instance id (Midnight S1). */
    private const RAID_INSTANCE_IDS = [
        'The Voidspire'        => 1307,
        'March on Quel\'Danas' => 1308,
        'The Dreamrift'        => 1314,
    ];

    /** Mythic+ dungeon name → Blizzard journal-instance id (Midnight S1 rotation). */
    private const DUNGEON_INSTANCE_IDS = [
        'Algeth\'ar Academy'      => 1201,
        'Magisters\' Terrace'     => 1300,
        'Maisara Caverns'         => 1315,
        'Nexus-Point Xenas'       => 1316,
        'Pit of Saron'            => 278,
        'Seat of the Triumvirate' => 945,
        'Skyreach'                => 476,
        'Windrunner Spire'        => 1299,
    ];

    public function run(): void
    {
        $path = database_path('seeders/data/midnight-s1-loot.json');
        if (! is_file($path)) {
            throw new RuntimeException("Loot dump missing: {$path}");
        }

        $raw = json_decode(file_get_contents($path), true);
        $items = $raw['data']['items'] ?? null;
        if (! is_array($items)) {
            throw new RuntimeException('Loot dump has no data.items array.');
        }

        $itemRows = [];
        $classRows = [];
        $skipped = 0;

        foreach ($items as $item) {
            $row = $this->buildItemRow($item);
            if ($row === null) {
                $skipped++;
                continue;
            }
            $itemRows[$row['id']] = $row;

            foreach ((array) ($item['allowableClass'] ?? []) as $class) {
                $classRows[] = ['season_item_id' => $row['id'], 'class_name' => $class];
            }
        }

        $this->command?->info('Parsed ' . count($itemRows) . " items, skipped {$skipped} (Delves/unknown source).");

        DB::transaction(function () use ($itemRows, $classRows) {
            $now = now();
            $upsertRows = array_map(
                fn (array $r) => $r + ['created_at' => $now, 'updated_at' => $now],
                array_values($itemRows)
            );

            foreach (array_chunk($upsertRows, 200) as $chunk) {
                DB::table('season_items')->upsert(
                    $chunk,
                    ['id'],
                    ['name', 'icon', 'inventory_type', 'armor_type', 'weapon_type',
                     'role', 'stats', 'source_type', 'source_slug', 'encounter_slug',
                     'encounter_id', 'boss_name', 'season_slug', 'is_tier', 'updated_at']
                );
            }

            // Wipe-and-replace pivot per seeded item — class allowance is
            // authoritative from this dump.
            $itemIds = array_keys($itemRows);
            DB::table('season_item_class')->whereIn('season_item_id', $itemIds)->delete();
            foreach (array_chunk($classRows, 500) as $chunk) {
                DB::table('season_item_class')->insert($chunk);
            }
        });

        $this->command?->info('Inserted ' . count($itemRows) . ' season items, ' . count($classRows) . ' class allowances.');
    }

    /**
     * Map a raw API item entry to a season_items row. Returns null for entries
     * we choose not to seed (Delves) or that lack the data we require.
     *
     * @return array<string, mixed>|null
     */
    private function buildItemRow(array $item): ?array
    {
        $sourceType = $item['source']['type'] ?? null;
        $sourceName = $item['source']['name'] ?? null;
        $itemId     = (int) ($item['id'] ?? 0);

        if ($itemId <= 0 || ! $sourceType || ! $sourceName) {
            return null;
        }

        // Skip Delves per product decision.
        if ($sourceType === 'Delves') {
            return null;
        }

        [$ourSourceType, $sourceSlug, $isTier] = $this->resolveSource($sourceType, $sourceName);
        if ($sourceSlug === null) {
            return null;
        }

        $encounterIds = $item['_metadata']['encounterIds'] ?? [];
        $firstEncounter = isset($encounterIds[0]) ? (int) $encounterIds[0] : null;

        return [
            'id'             => $itemId,
            'name'           => (string) ($item['name'] ?? ''),
            'icon'           => $this->buildIconUrl($item['icon'] ?? null),
            'inventory_type' => $item['inventoryType'] ?? null,
            'armor_type'     => $item['armorType'] ?? null,
            'weapon_type'    => isset($item['weaponType']) ? (int) $item['weaponType'] : null,
            'role'           => isset($item['role']) ? json_encode($item['role']) : null,
            'stats'          => isset($item['stats']) ? json_encode($item['stats']) : null,
            'source_type'    => $ourSourceType,
            'source_slug'    => $sourceSlug,
            'encounter_slug' => $firstEncounter !== null ? "encounter-{$firstEncounter}" : null,
            'encounter_id'   => $firstEncounter,
            'boss_name'      => $item['source']['boss'] ?? null,
            'season_slug'    => self::SEASON_SLUG,
            'is_tier'        => $isTier,
        ];
    }

    /**
     * Resolve the raw source label → (source_type, source_slug, is_tier).
     * Returns [null, null, false] if the source isn't recognized.
     *
     * @return array{0:?string, 1:?string, 2:bool}
     */
    private function resolveSource(string $sourceType, string $sourceName): array
    {
        if ($sourceType === 'Catalyst') {
            return ['catalyst', 'catalyst-' . self::SEASON_SLUG, true];
        }

        if ($sourceType === 'Raids') {
            $instanceId = self::RAID_INSTANCE_IDS[$sourceName] ?? null;
            return $instanceId
                ? ['raid', "instance-{$instanceId}", false]
                : [null, null, false];
        }

        if ($sourceType === 'Dungeons') {
            $instanceId = self::DUNGEON_INSTANCE_IDS[$sourceName] ?? null;
            return $instanceId
                ? ['dungeon', "instance-{$instanceId}", false]
                : [null, null, false];
        }

        return [null, null, false];
    }

    private function buildIconUrl(?string $iconName): ?string
    {
        if (! $iconName) {
            return null;
        }
        return "https://wow.zamimg.com/images/wow/icons/large/{$iconName}.jpg";
    }
}
