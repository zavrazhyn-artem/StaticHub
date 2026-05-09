<?php

declare(strict_types=1);

namespace App\Services\Gear;

use App\Models\SeasonItem;
use App\Services\Blizzard\BlizzardAuthService;
use App\Services\Blizzard\BlizzardGameDataApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Walks every gear-producing profession's current-expansion skill tier
 * and seeds craftable equipment into the season_items catalog with
 * is_craftable=true. Re-runnable: existing rows are upserted, so a season
 * patch that adds new recipes just needs another command run.
 *
 * Stat strategy for crafted gear: preview_item.stats from the BNet item
 * endpoint reliably gives primary + stamina (the "spec" portion of the
 * stat budget). Secondaries are user-chosen via Missive of XXX at craft
 * time, so we don't store secondary VALUES — just the secondary_pool of
 * which two-of-four the user can pick. The aggregate computes the
 * secondary contribution from a flat budget at the chosen ilvl.
 */
final class CraftableItemSyncService
{
    /**
     * Profession IDs that produce equipment. Other professions (Cooking,
     * Alchemy, Herbalism, etc.) only craft consumables/materials and are
     * skipped to keep the API budget reasonable.
     */
    private const GEAR_PROFESSION_IDS = [
        164, // Blacksmithing  → Plate armor + 1H/2H weapons
        165, // Leatherworking → Leather + Mail armor
        171, // Alchemy        → Phial trinkets (yes, they exist)
        197, // Tailoring      → Cloth armor + cloak
        202, // Engineering    → Goggles, ranged, trinkets
        333, // Enchanting     → Rings (some seasons)
        755, // Jewelcrafting  → Necks + rings + trinkets
        773, // Inscription    → Off-hands, trinkets
    ];


    /**
     * BNet inventory_type.type → the human-readable label the rest of the
     * catalog uses (existing season_items rows store 'Head', 'Off Hand',
     * 'One-Hand', etc.). The slot picker matches on these labels via
     * SeasonItemBuilder::inventoryTypesForSlot, so crafted rows MUST use
     * the same vocabulary or they'll never surface in the picker.
     */
    private const INVENTORY_TYPE_TO_LABEL = [
        'HEAD'           => 'Head',
        'NECK'           => 'Neck',
        'SHOULDER'       => 'Shoulder',
        'CLOAK'          => 'Back',
        'CHEST'          => 'Chest',
        'ROBE'           => 'Chest',
        'WRIST'          => 'Wrist',
        'HAND'           => 'Hands',
        'WAIST'          => 'Waist',
        'LEGS'           => 'Legs',
        'FEET'           => 'Feet',
        'FINGER'         => 'Finger',
        'TRINKET'        => 'Trinket',
        'WEAPON'         => 'One-Hand',
        'WEAPONMAINHAND' => 'Main Hand',
        'TWOHWEAPON'     => 'Two-Hand',
        'WEAPONOFFHAND'  => 'Off Hand',
        'SHIELD'         => 'Shield',
        'HOLDABLE'       => 'Off Hand',
        'RANGED'         => 'Ranged',
        'RANGEDRIGHT'    => 'Ranged',
    ];

    /**
     * Armor subclass label → class roster that can equip it. Plate-only
     * crafted gear shouldn't appear in a Mage's picker, so we seed the
     * season_item_class pivot per crafted armor row.
     *
     * Universal slots (Neck, Back, Finger, Trinket) bypass this and link
     * to all 13 classes — handled separately in classListForItem.
     */
    private const ARMOR_CLASSES = [
        'Plate'   => ['Death Knight', 'Paladin', 'Warrior'],
        'Mail'    => ['Hunter', 'Shaman', 'Evoker'],
        'Leather' => ['Demon Hunter', 'Druid', 'Monk', 'Rogue'],
        'Cloth'   => ['Mage', 'Priest', 'Warlock'],
    ];

    private const ALL_CLASSES = [
        'Death Knight', 'Demon Hunter', 'Druid', 'Evoker', 'Hunter',
        'Mage', 'Monk', 'Paladin', 'Priest', 'Rogue', 'Shaman', 'Warlock', 'Warrior',
    ];

    /** Universal slots — equippable by every class regardless of armor type. */
    private const UNIVERSAL_SLOT_LABELS = ['Neck', 'Back', 'Finger', 'Trinket'];

    /** Default secondary pool — most crafted items roll any 2 of these 4. */
    private const DEFAULT_SECONDARY_POOL = ['crit', 'haste', 'mastery', 'versatility'];

    /**
     * Modified-crafting reagent slot type for "Customize Secondary Stats"
     * (the Missive of XXX socket). When a recipe has this slot, the user
     * picks 2 of 4 secondaries at craft time. When absent, the item has
     * fixed secondaries baked into the BNet stats payload — no picker.
     */
    private const MISSIVE_SLOT_TYPE_ID = 393;

    public function __construct(
        private readonly BlizzardGameDataApiService $api,
        private readonly BlizzardAuthService $auth,
    ) {}

    /**
     * @return array{seen:int, equipment:int, upserted:int, skipped:int, errors:int}
     */
    public function sync(?\Closure $onProgress = null): array
    {
        $stats = ['seen' => 0, 'equipment' => 0, 'upserted' => 0, 'skipped' => 0, 'errors' => 0];

        foreach (self::GEAR_PROFESSION_IDS as $profId) {
            $tierId = null;
            try {
                $tierId = $this->api->getMidnightProfessionTier($profId);
            } catch (Throwable $e) {
                Log::warning("CraftableItemSyncService: profession {$profId} tier lookup failed", ['error' => $e->getMessage()]);
                $stats['errors']++;
                continue;
            }

            if ($tierId === null) {
                $onProgress?->__invoke("prof {$profId}: no current-expansion tier, skipping");
                continue;
            }

            try {
                $recipes = $this->api->getRecipesFromTier($profId, $tierId);
            } catch (Throwable $e) {
                Log::warning("CraftableItemSyncService: profession {$profId} tier {$tierId} recipe list failed", ['error' => $e->getMessage()]);
                $stats['errors']++;
                continue;
            }

            $onProgress?->__invoke("prof {$profId} tier {$tierId}: " . count($recipes) . " recipes");

            foreach ($recipes as $recipe) {
                $stats['seen']++;
                $name = $recipe['name']['en_US'] ?? $recipe['name'] ?? '';
                if (! is_string($name) || $name === '') continue;
                $recipeId = (int) ($recipe['id'] ?? 0);

                try {
                    $this->processRecipeName($recipeId, $name, $stats, $onProgress);
                } catch (Throwable $e) {
                    Log::warning("CraftableItemSyncService: recipe '{$name}' failed", ['error' => $e->getMessage()]);
                    $stats['errors']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Resolve a recipe name to its output item via BNet item search. The
     * recipe-details endpoint deliberately omits the crafted item id for
     * modern recipes (output is reagent-driven), but item names match the
     * recipe name 1:1 — we exploit that. We then filter to is_equippable
     * gear, dropping PROFESSION_TOOL/GEAR/NON_EQUIP results.
     */
    private function processRecipeName(int $recipeId, string $name, array &$stats, ?\Closure $onProgress): void
    {
        $itemId = $this->findItemIdForRecipeName($name);
        if ($itemId === null) {
            $stats['skipped']++;
            return;
        }

        $itemData = $this->api->getItemData($itemId);
        if (empty($itemData)) {
            $stats['skipped']++;
            return;
        }

        $invTypeRaw = strtoupper((string) ($itemData['inventory_type']['type'] ?? ''));
        $invLabel = self::INVENTORY_TYPE_TO_LABEL[$invTypeRaw] ?? null;
        if ($invLabel === null) {
            // Not equipment (e.g. CONSUMABLE, TRADEGOODS, BAG) — skip silently.
            $stats['skipped']++;
            return;
        }

        // Drop everything that isn't current-tier Epic crafted gear.
        // Rules out: levelling Rare/Uncommon crafts (Smuggler's Reinforced
        // line that maxes at ilvl ~250) and PvP gear (Thalassian Competitor
        // / Gladiator series — different scaling system, only meaningful in
        // PvP, can't be sparked into PvE relevance).
        $quality = strtoupper((string) ($itemData['quality']['type'] ?? ''));
        if ($quality !== 'EPIC') {
            $stats['skipped']++;
            return;
        }

        $stats['equipment']++;

        // Probe the recipe's reagent slots for a Missive socket — if one is
        // present the user picks 2 of 4 secondaries at craft time; otherwise
        // secondaries are baked-in (e.g. embellishment-locked items like
        // Arcanoweave Cloak that always roll Vers+Mastery). Drives whether
        // the picker shows the stat-choice modal in step 2.
        $userPicksSecondaries = $this->recipeHasMissiveSlot($recipeId);

        $icon = $this->resolveIcon($itemData);
        $previewStats = $itemData['preview_item']['stats'] ?? [];
        $baseIlvl = (int) ($itemData['preview_item']['level']['value'] ?? $itemData['level'] ?? 0);
        $armorType = $this->resolveArmorType($itemData);

        $realStats = $this->extractRealStats($previewStats);
        // BNet preview for a Missive-socket item returns ONE concrete
        // secondary (whatever the default missive is, e.g. Mastery=49 at
        // ilvl 246 for Silvermoon Mantle). Per missive mechanics both
        // chosen stats get the SAME value at craft time, so we extract
        // that one number as `secondary_per_stat_at_base` and strip it
        // from real_stats so the aggregator doesn't double-count it
        // alongside the user-chosen pair.
        if ($userPicksSecondaries) {
            // BNet preview shows BOTH default-missive secondaries with the
            // same value (e.g. crit=49 + mastery=49 for a 49-rated default).
            // Capture the value once, then strip every secondary so the
            // user's pick is the only contributor in the aggregator.
            $secondaryKeys = ['crit', 'haste', 'mastery', 'versatility'];
            foreach ($secondaryKeys as $k) {
                $v = (int) ($realStats[$k] ?? 0);
                if ($v > 0 && ! isset($realStats['secondary_per_stat_at_base'])) {
                    $realStats['secondary_per_stat_at_base'] = $v;
                }
                unset($realStats[$k]);
            }
            // Fall back to the 0.67×primary heuristic if BNet didn't
            // supply any secondary number for this preview (rare, but
            // keeps us from reporting zero secondaries on a real item).
            if (! isset($realStats['secondary_per_stat_at_base'])) {
                $primary = max(
                    (int) ($realStats['agility'] ?? 0),
                    (int) ($realStats['strength'] ?? 0),
                    (int) ($realStats['intellect'] ?? 0),
                );
                if ($primary > 0) {
                    $realStats['secondary_per_stat_at_base'] = (int) round($primary * 0.67);
                }
            }
        }

        // PvP gear was previously routed here under source_type='pvp';
        // dropped after observing it uses a separate Heraldry/PvP scaling
        // track (in-PvP-only ilvl 289 via Gladiator's Heraldry reagents)
        // that doesn't compete with PvE crafts. Quality filter above keeps
        // it out automatically since PvP gear ships at Rare quality.
        $sourceType = 'crafted';
        $sourceSlug = 'crafted-' . SeasonItem::CURRENT_SEASON_SLUG;

        // Direct DB upsert (matches MidnightS1LootSeeder pattern) — Eloquent's
        // updateOrCreate quietly drops the explicit `id` because the model is
        // configured for auto-increment, leaving the returned model with id=0.
        $now = now();
        \DB::table('season_items')->upsert([[
            'id'              => $itemId,
            'name'            => $name,
            'icon'            => $icon,
            'inventory_type'  => $invLabel,
            'armor_type'      => $this->labelArmorType($armorType),
            // All-roles so the picker shows crafted gear regardless of
            // spec — most crafted pieces are spec-agnostic and missives
            // let the user tilt the secondaries to taste.
            'role'            => json_encode(['Tank', 'DPS', 'Healer']),
            'stats'           => json_encode($realStats),
            'real_stats'      => json_encode($realStats),
            'base_item_level' => $baseIlvl > 0 ? $baseIlvl : null,
            'source_type'     => $sourceType,
            'source_slug'     => $sourceSlug,
            'encounter_slug'  => null,
            'encounter_id'    => null,
            'boss_name'       => null,
            'season_slug'     => SeasonItem::CURRENT_SEASON_SLUG,
            'is_tier'         => false,
            'is_craftable'    => true,
            'secondary_pool'  => $userPicksSecondaries ? json_encode(self::DEFAULT_SECONDARY_POOL) : null,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]],
            ['id'],
            ['name', 'icon', 'inventory_type', 'armor_type', 'role', 'stats',
             'real_stats', 'base_item_level', 'source_type', 'source_slug',
             'season_slug', 'is_craftable', 'secondary_pool', 'updated_at'],
        );

        $this->syncClassRoster($itemId, $invLabel, $armorType);

        $stats['upserted']++;
        $onProgress?->__invoke("  ✓ {$invLabel} {$name} (#{$itemId} ilvl {$baseIlvl})");
    }

    /**
     * Match the existing catalog's casing ('Plate', 'Mail', 'Leather',
     * 'Cloth') so reporting/filtering stays uniform across raid drops
     * and crafted rows.
     */
    private function labelArmorType(?string $type): ?string
    {
        if ($type === null) return null;
        return match (strtolower($type)) {
            'plate' => 'Plate',
            'mail' => 'Mail',
            'leather' => 'Leather',
            'cloth' => 'Cloth',
            default => null,
        };
    }

    /**
     * Wipe + reinsert season_item_class rows for a crafted item. Universal
     * slots (Neck/Back/Finger/Trinket + weapons we can't subclass-restrict)
     * link to every class; armor pieces only link to the classes that can
     * equip that armor type.
     */
    private function syncClassRoster(int $itemId, string $invLabel, ?string $armorType): void
    {
        $classes = self::ALL_CLASSES;
        if (in_array($invLabel, ['Head', 'Shoulder', 'Chest', 'Wrist', 'Hands', 'Waist', 'Legs', 'Feet'], true)) {
            $classes = self::ARMOR_CLASSES[$this->labelArmorType($armorType) ?? ''] ?? self::ALL_CLASSES;
        }

        \DB::table('season_item_class')->where('season_item_id', $itemId)->delete();
        $rows = array_map(fn ($cls) => ['season_item_id' => $itemId, 'class_name' => $cls], $classes);
        if (! empty($rows)) {
            \DB::table('season_item_class')->insert($rows);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $previewStats
     * @return array<string,int>
     */
    private function extractRealStats(array $previewStats): array
    {
        $out = [];
        $map = [
            'INTELLECT'   => 'intellect',
            'AGILITY'     => 'agility',
            'STRENGTH'    => 'strength',
            'STAMINA'     => 'stamina',
            'CRIT_RATING' => 'crit',
            'HASTE_RATING' => 'haste',
            'MASTERY_RATING' => 'mastery',
            'VERSATILITY' => 'versatility',
        ];
        foreach ($previewStats as $entry) {
            $type = strtoupper((string) ($entry['type']['type'] ?? ''));
            $key = $map[$type] ?? null;
            if ($key === null) continue;
            $value = (int) ($entry['value'] ?? 0);
            if ($value > 0) $out[$key] = $value;
        }
        return $out;
    }

    private function resolveIcon(array $itemData): ?string
    {
        $itemId = (int) ($itemData['id'] ?? 0);
        if ($itemId <= 0) return null;

        // BNet item endpoint doesn't expose the icon slug directly — we
        // have to follow media.key.href to /data/wow/media/item/{id}.
        // Costs one extra request per crafted item but it's the only
        // first-party way to get a stable icon URL.
        return $this->api->getItemIcon($itemId, $itemData);
    }

    /**
     * Does the recipe expose a "Customize Secondary Stats" slot
     * (Missive of XXX socket)? If yes the player chooses 2 of 4
     * secondaries at craft time; if no the item ships with whatever
     * secondaries Blizzard baked into the template.
     */
    private function recipeHasMissiveSlot(int $recipeId): bool
    {
        if ($recipeId <= 0) return false;
        try {
            $details = $this->api->getRecipeDetails($recipeId);
        } catch (Throwable $e) {
            return false;
        }
        foreach ($details['modified_crafting_slots'] ?? [] as $slot) {
            if ((int) ($slot['slot_type']['id'] ?? 0) === self::MISSIVE_SLOT_TYPE_ID) {
                return true;
            }
        }
        return false;
    }

    /**
     * Search BNet items by exact name and return the first equippable
     * non-tool match. Returns null when nothing matches (tools-only,
     * legacy items, or recipes whose output didn't make it into the DB).
     *
     * Search results are sometimes prefix-fuzzy; we re-check name equality
     * before accepting so 'Sun-Blessed Hammer' doesn't match 'Sun-Blessed
     * Hammer of Awakening'.
     */
    private function findItemIdForRecipeName(string $name): ?int
    {
        $region = $this->auth->getRegion();
        $token = $this->auth->getAccessToken();
        $url = "https://{$region}.api.blizzard.com/data/wow/search/item"
            . "?_page=1&_pageSize=20"
            . "&namespace=static-{$region}"
            . "&name.en_US=" . urlencode($name);

        $response = Http::withToken($token)->timeout(20)->get($url);
        if ($response->failed()) return null;

        foreach ($response->json('results', []) as $hit) {
            $d = $hit['data'] ?? [];
            $itemName = $d['name']['en_US'] ?? null;
            if ($itemName !== $name) continue;

            $invType = strtoupper((string) ($d['inventory_type']['type'] ?? ''));
            // Drop profession tools, off-hand profession kits, and items
            // that aren't gear at all.
            if (in_array($invType, ['PROFESSION_TOOL', 'PROFESSION_GEAR', 'NON_EQUIP', ''], true)) continue;
            if (! isset(self::INVENTORY_TYPE_TO_LABEL[$invType])) continue;

            $id = (int) ($d['id'] ?? 0);
            if ($id > 0) return $id;
        }
        return null;
    }

    private function resolveArmorType(array $itemData): ?string
    {
        $subclass = $itemData['item_subclass']['name']['en_US']
            ?? $itemData['item_subclass']['name']
            ?? null;
        return is_string($subclass) ? $subclass : null;
    }
}
