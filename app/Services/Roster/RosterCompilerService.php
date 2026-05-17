<?php

declare(strict_types=1);

namespace App\Services\Roster;

use App\Data\Roster\CharacterDataDTO;
use App\Data\Roster\CharacterWeeklyDataDTO;
use App\Models\Character;
use App\Services\StaticGroup\RosterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Compiles fetched API payloads into the two JSON columns on characters:
 *   - character_data        (persistent profile/gear/progression facts)
 *   - character_weekly_data (resets at weekly reset)
 *
 * Two entry points, one per import job, both atomic via JSON_MERGE_PATCH so
 * Bnet and Rio compiles can race each other safely:
 *   - compileAndPersistBnet(Character, array $bnet)
 *   - compileAndPersistRio(Character, array $rio)
 *
 * No network calls — all data comes from the caller's just-fetched payloads.
 */
final class RosterCompilerService
{
    public function __construct(
        private readonly GearAuditService       $gearAudit,
        private readonly InstanceDataService    $instanceData,
        private readonly VaultDataService       $vaultData,
        private readonly ProgressionDataService $progression,
        private readonly CollectionDataService  $collection,
        private readonly RosterService          $rosterService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Compile bnet-derivable fields and atomically merge into characters table.
     *
     * `$bnet` is a normalized array of just-fetched Bnet payloads:
     *   ['bnet_profile' => [...], 'bnet_equipment' => [...], 'bnet_media' => [...],
     *    'bnet_mplus' => [...], 'bnet_raid' => [...], 'bnet_achievement_statistics' => [...],
     *    'bnet_completed_quests' => [...], 'bnet_pvp_summary' => [...], 'bnet_reputations' => [...],
     *    'bnet_titles' => [...], 'bnet_mounts' => [...], 'bnet_pets' => [...]]
     *
     * Accumulators (`bnet_equipment_by_spec`, `vault_weekly_snapshot`) are read
     * from the Character model and updated by the caller before invoking this method.
     */
    public function compileAndPersistBnet(Character $character, array $bnet): void
    {
        $region = strtolower((string) ($character->realm?->region ?? 'eu'));
        $this->setRegion($region);

        $profile   = $bnet['bnet_profile']   ?? [];
        $equipment = $bnet['bnet_equipment'] ?? [];
        $media     = $bnet['bnet_media']     ?? [];
        $mplus     = $bnet['bnet_mplus']     ?? [];
        $achStats  = $bnet['bnet_achievement_statistics'] ?? [];
        $quests    = $bnet['bnet_completed_quests'] ?? [];
        $pvpSum    = $bnet['bnet_pvp_summary'] ?? [];
        $reps      = $bnet['bnet_reputations'] ?? [];
        $titles    = $bnet['bnet_titles'] ?? [];
        $mounts    = $bnet['bnet_mounts'] ?? [];
        $pets      = $bnet['bnet_pets'] ?? [];
        $raidData  = $bnet['bnet_raid'] ?? [];
        $specData  = $bnet['bnet_specialization'] ?? [];

        $equipmentBySpec = $character->bnet_equipment_by_spec ?? [];
        $snapshot        = $character->vault_weekly_snapshot ?? [];

        $equippedItems = $equipment['equipped_items'] ?? [];

        $completedQuestIds = $this->progression->buildCompletedQuestSet($quests);
        $achStatsIndex     = $this->progression->indexAchievementStatistics($achStats);

        $preyWeekly   = $this->progression->resolvePreyWeekly($completedQuestIds);
        $weeklyQuests = $this->progression->resolveWeeklyQuests($completedQuestIds);

        $weekRegularMythic = $this->vaultData->resolveWeekRegularMythicDungeons($achStatsIndex);

        [$compiledEquipmentBySpec, $ilvlBySpec, $gearAuditBySpec] = $this->compilePerSpecGear($equipmentBySpec);

        $charPatch = [
            'avatar_url'             => $this->resolveAvatarUrl($media),
            'class'                  => $this->resolveClass($profile),
            'class_id'               => $this->resolveClassId($profile),
            'spec_id'                => $this->resolveSpecId($profile),
            'combat_role'            => $this->resolveRole($profile),
            'equipped_ilvl'          => $this->resolveEquippedIlvl($profile),
            'mythic_rating'          => $this->instanceData->resolveMythicRating($mplus),
            'season_heroic_dungeons' => $this->instanceData->resolveSeasonHeroicDungeons($achStatsIndex),
            'missing_enchants_slots'     => $this->gearAudit->resolveMissingEnchants($equippedItems),
            'low_quality_enchants_slots' => $this->gearAudit->resolveLowQualityEnchants($equippedItems),
            'empty_sockets_count'        => $this->gearAudit->resolveEmptySockets($equippedItems),
            'upgrades_missing'       => $this->gearAudit->resolveTotalUpgradesMissing($equippedItems),
            'sparks_equipped'        => $this->gearAudit->resolveSparksEquipped($equippedItems),
            'tier_pieces'            => $this->gearAudit->resolveTierPieces($equippedItems),
            'tier_ilvls'             => $this->gearAudit->resolveTierIlvls($equippedItems),
            'equipment'              => $this->gearAudit->resolveEquipment($equippedItems),
            'season_delves'          => $this->progression->resolveSeasonDelves($achStatsIndex),
            'coffer_keys'            => $this->progression->resolveCofferKeys($achStatsIndex),
            'cutting_edge'           => $this->progression->resolveCuttingEdge($achStats),
            'ahead_of_the_curve'     => $this->progression->resolveAheadOfTheCurve($achStats),
            'achievement_points'     => (int) ($profile['achievement_points'] ?? 0),
            'crests'                 => $this->progression->resolveCrests($achStatsIndex),
            'mounts_count'           => count($mounts['mounts'] ?? []),
            'unique_pets'            => $this->collection->resolveUniquePets($pets),
            'lvl_25_pets'            => $this->collection->resolveLvl25Pets($pets),
            'titles_count'           => count($titles['titles'] ?? []),
            'honor_level'            => (int) ($pvpSum['honor_level'] ?? 0),
            'honorable_kills'        => (int) ($pvpSum['honorable_kills'] ?? 0),
            'pvp_brackets'           => $this->collection->resolvePvpBrackets($pvpSum),
            'renown'                 => $this->collection->resolveRenown($reps),
            'embellished_items'      => $this->gearAudit->resolveEmbellishedItems($equippedItems),
            'spark_gear'             => $this->gearAudit->resolveSparkGear($equippedItems),
            'equipment_by_spec'      => $compiledEquipmentBySpec ?: null,
            'ilvl_by_spec'           => $ilvlBySpec ?: null,
            'gear_audit_by_spec'     => $gearAuditBySpec ?: null,
            'raid_progression'       => $this->instanceData->resolveRaids($raidData),
            'talent_loadout_code'    => $this->resolveTalentLoadoutCode($specData),
            'talent_loadout_codes_by_spec' => $this->resolveTalentLoadoutCodesBySpec($specData),
        ];

        $weeklyPatch = [
            'week_regular_mythic' => $weekRegularMythic,
            'raids'               => $this->instanceData->resolveWeeklyRaidKills($achStatsIndex),
            'vault_world_runs'    => $this->vaultData->resolveVaultWorldRuns($achStats, $snapshot, $weeklyQuests, $preyWeekly),
            'vault_raid_slots'    => $this->vaultData->resolveVaultRaidSlots($achStatsIndex),
            'prey_weekly'         => $preyWeekly,
            'weekly_quests'       => $weeklyQuests,
            'weekly_event_done'   => $this->progression->resolveWeeklyEventDone($completedQuestIds),
            'week_delves'         => $this->progression->resolveWeekDelves($achStats, $snapshot),
        ];

        // Resolve active_spec from raw bnet profile — kept on the characters row
        // (alongside character_data.spec_id) so the My Characters page list can
        // show spec without unpacking the JSON blob on every render.
        $activeSpec = is_array($profile['active_spec'] ?? null)
            ? ($profile['active_spec']['name'] ?? null)
            : ($profile['active_spec'] ?? null);
        if ($activeSpec === null) {
            $activeSpec = is_array($profile['active_specialization'] ?? null)
                ? ($profile['active_specialization']['name'] ?? null)
                : ($profile['active_specialization'] ?? null);
        }

        // equipped_item_level column drives the My Characters page ilvl badge.
        // Mirror character_data.equipped_ilvl into the column so we don't keep
        // two stale-rate sources of truth.
        $equippedIlvl = $profile['equipped_item_level'] ?? null;

        $this->mergePatchCharacter($character->id, $charPatch, $weeklyPatch, $activeSpec, $equippedIlvl);

        $character->refresh();

        $staticIds = $character->statics()->pluck('statics.id');
        foreach ($staticIds as $staticId) {
            $this->rosterService->autoSetMainSpecIfMissing($character, (int) $staticId);
        }
    }

    /**
     * Compile rio-derivable fields (just 2 weekly fields) and atomically merge.
     *
     * Reads `week_regular_mythic` from the existing `character_weekly_data`
     * (set by the most recent BnetImportJob) so vault math has the bnet baseline
     * even when this job runs first or after a delay.
     */
    public function compileAndPersistRio(Character $character, array $rio): void
    {
        $region = strtolower((string) ($character->realm?->region ?? 'eu'));
        $this->setRegion($region);

        $weeklyExisting    = $character->character_weekly_data ?? [];
        $weekRegularMythic = (int) ($weeklyExisting['week_regular_mythic'] ?? 0);

        $weeklyPatch = [
            'weekly_runs_count' => $this->instanceData->resolveWeeklyRunsCount($rio),
            'vault_weekly_runs' => $this->vaultData->resolveVaultWeeklyRuns($rio, $weekRegularMythic),
        ];

        $this->mergePatchCharacter($character->id, [], $weeklyPatch, null, null);

        $character->refresh();
    }

    /**
     * Atomic JSON_MERGE_PATCH for character_data and character_weekly_data,
     * plus optional active_spec / equipped_item_level column updates — all in
     * a single UPDATE so concurrent bnet+rio fetches can never lose each
     * other's writes.
     *
     * `$charPatch` and `$weeklyPatch` are plain assoc arrays of fields to merge.
     * Empty arrays are skipped (no-op for that JSON column).
     */
    private function mergePatchCharacter(
        int $characterId,
        array $charPatch,
        array $weeklyPatch,
        ?string $activeSpec,
        ?float $equippedIlvl,
    ): void {
        $sets = [];
        $params = [];

        if ($charPatch !== []) {
            $sets[] = "character_data = JSON_MERGE_PATCH(COALESCE(character_data, '{}'), CAST(? AS JSON))";
            $params[] = json_encode($charPatch, JSON_UNESCAPED_UNICODE);
        }

        if ($weeklyPatch !== []) {
            $sets[] = "character_weekly_data = JSON_MERGE_PATCH(COALESCE(character_weekly_data, '{}'), CAST(? AS JSON))";
            $params[] = json_encode($weeklyPatch, JSON_UNESCAPED_UNICODE);
        }

        if ($activeSpec !== null) {
            $sets[] = "active_spec = ?";
            $params[] = $activeSpec;
        }

        if ($equippedIlvl !== null) {
            $sets[] = "equipped_item_level = ?";
            $params[] = (int) $equippedIlvl;
        }

        if ($sets === []) {
            return;
        }

        $sets[] = "updated_at = NOW()";
        $params[] = $characterId;

        DB::update("UPDATE characters SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    }

    // =========================================================================
    // REGION
    // =========================================================================

    private function setRegion(string $region): void
    {
        $this->progression->setRegion($region);
        $this->vaultData->setRegion($region);
        $this->instanceData->setRegion($region);
    }

    // =========================================================================
    // MEDIA
    // =========================================================================

    private function resolveAvatarUrl(array $media): ?string
    {
        foreach ($media['assets'] ?? [] as $asset) {
            if (($asset['key'] ?? '') === 'avatar') {
                $url = (string) ($asset['value'] ?? '');
                return $url !== '' ? $url : null;
            }
        }
        return null;
    }

    // =========================================================================
    // PROFILE
    // =========================================================================

    private function resolveClass(array $profile): ?string
    {
        $name = (string) ($profile['character_class']['name'] ?? $profile['class'] ?? '');
        return $name !== '' ? $name : null;
    }

    private function resolveClassId(array $profile): ?int
    {
        $id = $profile['character_class']['id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    private function resolveSpecId(array $profile): ?int
    {
        $id = $profile['active_specialization']['id']
            ?? $profile['active_spec']['id']
            ?? null;
        return $id !== null ? (int) $id : null;
    }

    private function resolveEquippedIlvl(array $profile): ?float
    {
        $raw = $profile['equipped_item_level'] ?? null;
        return $raw !== null ? (float) $raw : null;
    }

    private function resolveRole(array $profile): ?string
    {
        $role = $profile['active_spec']['role']['type']
            ?? $profile['active_specialization']['role']['type']
            ?? $profile['specializations']['active_specialization']['role']['type']
            ?? null;

        if ($role !== null) {
            return strtoupper((string) $role);
        }

        $specName = strtolower(
            (string) ($profile['active_spec']['name']
                ?? $profile['active_specialization']['name']
                ?? '')
        );

        if ($specName === '') {
            return null;
        }

        return $this->deriveRoleFromSpecName($specName);
    }

    private function resolveTalentLoadoutCode(array $specData): ?string
    {
        $activeSpecId = $specData['active_specialization']['id'] ?? null;
        if ($activeSpecId === null) {
            return null;
        }

        $bySpec = $this->resolveTalentLoadoutCodesBySpec($specData);
        return $bySpec[(int) $activeSpecId] ?? null;
    }

    /**
     * Map of [spec_id => active loadout code] across all specs the character has set up.
     * Each spec's "active" loadout = the one with `is_active: true` in its loadouts[].
     *
     * @return array<int, string>
     */
    private function resolveTalentLoadoutCodesBySpec(array $specData): array
    {
        $bySpec = [];
        foreach ($specData['specializations'] ?? [] as $spec) {
            $specId = $spec['specialization']['id'] ?? null;
            if ($specId === null) {
                continue;
            }

            foreach ($spec['loadouts'] ?? [] as $loadout) {
                if (($loadout['is_active'] ?? false) !== true) {
                    continue;
                }
                $code = $loadout['talent_loadout_code'] ?? null;
                if ($code !== null && $code !== '') {
                    $bySpec[(int) $specId] = (string) $code;
                }
                break;
            }
        }

        return $bySpec;
    }

    /**
     * @return array{array, array, array} [equipmentBySpec, ilvlBySpec, gearAuditBySpec]
     */
    private function compilePerSpecGear(array $equipmentBySpec): array
    {
        $compiledEquipment = [];
        $ilvlBySpec        = [];
        $gearAuditBySpec   = [];

        foreach ($equipmentBySpec as $specName => $specEquipmentRaw) {
            $specItems = $specEquipmentRaw['equipped_items'] ?? [];
            if ($specItems === []) {
                continue;
            }

            $compiledEquipment[$specName] = $this->gearAudit->resolveEquipment($specItems);
            $ilvlBySpec[$specName]        = $this->resolveEquippedIlvlFromItems($specItems);
            $gearAuditBySpec[$specName]   = [
                'missing_enchants_slots'     => $this->gearAudit->resolveMissingEnchants($specItems),
                'low_quality_enchants_slots' => $this->gearAudit->resolveLowQualityEnchants($specItems),
                'empty_sockets_count'        => $this->gearAudit->resolveEmptySockets($specItems),
                'upgrades_missing'           => $this->gearAudit->resolveTotalUpgradesMissing($specItems),
                'sparks_equipped'            => $this->gearAudit->resolveSparksEquipped($specItems),
                'tier_pieces'                => $this->gearAudit->resolveTierPieces($specItems),
                'tier_ilvls'                 => $this->gearAudit->resolveTierIlvls($specItems),
                'embellished_items'          => $this->gearAudit->resolveEmbellishedItems($specItems),
                'spark_gear'                 => $this->gearAudit->resolveSparkGear($specItems),
            ];
        }

        return [$compiledEquipment, $ilvlBySpec, $gearAuditBySpec];
    }

    private function resolveEquippedIlvlFromItems(array $equippedItems): ?float
    {
        if ($equippedItems === []) {
            return null;
        }

        $totalIlvl  = 0;
        $slotCount  = 0;
        $hasTwoHand = false;

        foreach ($equippedItems as $item) {
            $ilvl = (int) ($item['level']['value'] ?? 0);
            if ($ilvl === 0) {
                continue;
            }

            $slotType = strtoupper((string) ($item['slot']['type'] ?? ''));

            // Skip shirt and tabard
            if (in_array($slotType, ['SHIRT', 'TABARD'], true)) {
                continue;
            }

            $invType = strtoupper((string) ($item['inventory_type']['type'] ?? ''));
            if ($invType === 'TWOHWEAPON') {
                $hasTwoHand = true;
                // 2H counts as two slots
                $totalIlvl += $ilvl * 2;
                $slotCount += 2;
            } else {
                $totalIlvl += $ilvl;
                $slotCount++;
            }
        }

        if ($slotCount === 0) {
            return null;
        }

        return round($totalIlvl / max($slotCount, 16), 2);
    }

    private function deriveRoleFromSpecName(string $specName): string
    {
        static $tanks   = ['protection', 'guardian', 'blood', 'brewmaster', 'vengeance'];
        static $healers = ['restoration', 'holy', 'discipline', 'mistweaver', 'preservation'];

        foreach ($tanks as $keyword) {
            if (str_contains($specName, $keyword)) {
                return 'TANK';
            }
        }
        foreach ($healers as $keyword) {
            if (str_contains($specName, $keyword)) {
                return 'HEALER';
            }
        }
        return 'DPS';
    }
}
