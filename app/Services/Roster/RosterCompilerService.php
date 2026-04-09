<?php

declare(strict_types=1);

namespace App\Services\Roster;

use App\Data\Roster\CharacterDataDTO;
use App\Data\Roster\CharacterWeeklyDataDTO;
use App\Models\Character;
use App\Models\ServiceRawData;
use App\Services\StaticGroup\RosterService;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates compilation of a ServiceRawData record into two DTOs:
 *   - CharacterDataDTO      → characters.character_data   (persistent)
 *   - CharacterWeeklyDataDTO → characters.character_weekly_data (resets weekly)
 *
 * No network calls — all data comes from previously fetched JSON.
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

    public function compileAndPersist(Character $character): void
    {
        $rawData = $character->serviceRawData()->first();

        if (!$rawData) {
            Log::warning('No raw data for character', ['id' => $character->id]);
            return;
        }

        $region = strtolower((string) ($character->realm?->region ?? 'eu'));
        $this->setRegion($region);

        [$charData, $weeklyData] = $this->compile($rawData);

        $charArray   = json_decode(json_encode($charData), true);
        $weeklyArray = json_decode(json_encode($weeklyData), true);

        // Extract active_spec from raw bnet profile
        $bnetProfile = $rawData->bnet_profile ?? [];
        $activeSpec = is_array($bnetProfile['active_spec'] ?? null)
            ? ($bnetProfile['active_spec']['name'] ?? null)
            : ($bnetProfile['active_spec'] ?? null);
        if ($activeSpec === null) {
            $activeSpec = is_array($bnetProfile['active_specialization'] ?? null)
                ? ($bnetProfile['active_specialization']['name'] ?? null)
                : ($bnetProfile['active_specialization'] ?? null);
        }

        $character->update([
            'character_data'        => $charArray,
            'character_weekly_data' => $weeklyArray,
            'active_spec'           => $activeSpec ?? $character->active_spec,
        ]);

        $character->refresh();

        // Auto-set main spec for each static
        $staticIds = $character->statics()->pluck('statics.id');
        foreach ($staticIds as $staticId) {
            $this->rosterService->autoSetMainSpecIfMissing($character, (int) $staticId);
        }
    }

    /**
     * @return array{CharacterDataDTO, CharacterWeeklyDataDTO}
     */
    public function compile(ServiceRawData $rawData): array
    {
        $profile   = $rawData->bnet_profile   ?? [];
        $equipment = $rawData->bnet_equipment ?? [];
        $media     = $rawData->bnet_media     ?? [];
        $mplus     = $rawData->bnet_mplus     ?? [];
        $rio       = $rawData->rio_profile    ?? [];
        $achStats  = $rawData->bnet_achievement_statistics ?? [];
        $snapshot  = $rawData->vault_weekly_snapshot ?? [];
        $quests    = $rawData->bnet_completed_quests ?? [];
        $pvpSum    = $rawData->bnet_pvp_summary ?? [];
        $reps      = $rawData->bnet_reputations ?? [];
        $titles    = $rawData->bnet_titles ?? [];
        $mounts    = $rawData->bnet_mounts ?? [];
        $pets      = $rawData->bnet_pets ?? [];

        $equippedItems = $equipment['equipped_items'] ?? [];
        $rioItems      = $rio['gear']['items'] ?? [];

        $completedQuestIds = $this->progression->buildCompletedQuestSet($quests);
        $achStatsIndex     = $this->progression->indexAchievementStatistics($achStats);

        $preyWeekly   = $this->progression->resolvePreyWeekly($completedQuestIds);
        $weeklyQuests = $this->progression->resolveWeeklyQuests($completedQuestIds);

        $weekRegularMythic = $this->vaultData->resolveWeekRegularMythicDungeons($achStatsIndex);

        $charData = new CharacterDataDTO(
            avatar_url:             $this->resolveAvatarUrl($media),
            class:                  $this->resolveClass($profile),
            class_id:               $this->resolveClassId($profile),
            spec_id:                $this->resolveSpecId($profile),
            combat_role:            $this->resolveRole($profile),
            equipped_ilvl:          $this->resolveEquippedIlvl($profile),
            highest_ilvl_ever:      null,
            mythic_rating:          $this->instanceData->resolveMythicRating($mplus),
            season_heroic_dungeons: $this->instanceData->resolveSeasonHeroicDungeons($achStatsIndex),
            missing_enchants_slots:     $this->gearAudit->resolveMissingEnchants($equippedItems),
            low_quality_enchants_slots: $this->gearAudit->resolveLowQualityEnchants($equippedItems),
            empty_sockets_count:        $this->gearAudit->resolveEmptySockets($equippedItems),
            upgrades_missing:       $this->gearAudit->resolveTotalUpgradesMissing($equippedItems),
            sparks_equipped:        $this->gearAudit->resolveSparksEquipped($equippedItems),
            tier_pieces:            $this->gearAudit->resolveTierPieces($equippedItems),
            tier_ilvls:             $this->gearAudit->resolveTierIlvls($equippedItems),
            equipment:              $this->gearAudit->resolveEquipment($equippedItems, $rioItems, $rawData),
            season_delves:          $this->progression->resolveSeasonDelves($achStatsIndex),
            coffer_keys:            $this->progression->resolveCofferKeys($achStatsIndex),
            cutting_edge:           $this->progression->resolveCuttingEdge($achStats),
            ahead_of_the_curve:     $this->progression->resolveAheadOfTheCurve($achStats),
            achievement_points:     (int) ($profile['achievement_points'] ?? 0),
            crests:                 $this->progression->resolveCrests($achStatsIndex),
            mounts_count:           count($mounts['mounts'] ?? []),
            unique_pets:            $this->collection->resolveUniquePets($pets),
            lvl_25_pets:            $this->collection->resolveLvl25Pets($pets),
            titles_count:           count($titles['titles'] ?? []),
            honor_level:            (int) ($pvpSum['honor_level'] ?? 0),
            honorable_kills:        (int) ($pvpSum['honorable_kills'] ?? 0),
            pvp_brackets:           $this->collection->resolvePvpBrackets($pvpSum),
            renown:                 $this->collection->resolveRenown($reps),
            embellished_items:      $this->gearAudit->resolveEmbellishedItems($equippedItems),
            spark_gear:             $this->gearAudit->resolveSparkGear($equippedItems),
        );

        $weeklyData = new CharacterWeeklyDataDTO(
            weekly_runs_count:   $this->instanceData->resolveWeeklyRunsCount($mplus, $rio),
            week_regular_mythic: $weekRegularMythic,
            raids:               $this->instanceData->resolveWeeklyRaidKills($achStatsIndex),
            vault_weekly_runs:   $this->vaultData->resolveVaultWeeklyRuns($rio, $weekRegularMythic),
            vault_world_runs:    $this->vaultData->resolveVaultWorldRuns($achStats, $snapshot, $weeklyQuests, $preyWeekly),
            vault_raid_slots:    $this->vaultData->resolveVaultRaidSlots($achStatsIndex),
            prey_weekly:         $preyWeekly,
            weekly_quests:       $weeklyQuests,
            weekly_event_done:   $this->progression->resolveWeeklyEventDone($completedQuestIds),
            week_delves:         $this->progression->resolveWeekDelves($achStats, $snapshot),
        );

        return [$charData, $weeklyData];
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
