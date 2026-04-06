<?php

declare(strict_types=1);

namespace App\Services\Roster;

use App\Data\Roster\CompiledRosterMemberDTO;
use App\Models\Character;
use App\Models\ServiceRawData;
use App\Services\StaticGroup\RosterService;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates compilation of a ServiceRawData record into a single,
 * frontend-ready DTO by delegating to focused sub-services.
 *
 * No network calls are made here — all data comes from previously fetched
 * and validated JSON stored in the database.
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
     * Compile raw data for a character and persist the result.
     */
    public function compileAndPersist(Character $character): void
    {
        $rawData = $character->serviceRawData()->first();

        if (!$rawData) {
            Log::warning('No raw data for character', ['id' => $character->id]);
            return;
        }

        $compiled = $this->compile($rawData);
        $compiledArray = json_decode(json_encode($compiled), true);

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
            'compiled_data' => $compiledArray,
            'active_spec' => $activeSpec ?? $character->active_spec,
        ]);

        $character->refresh();

        // Auto-set main spec for each static
        $staticIds = $character->statics()->pluck('statics.id');
        foreach ($staticIds as $staticId) {
            $this->rosterService->autoSetMainSpecIfMissing($character, (int) $staticId);
        }
    }

    public function compile(ServiceRawData $rawData): CompiledRosterMemberDTO
    {
        $profile   = $rawData->bnet_profile   ?? [];
        $equipment = $rawData->bnet_equipment ?? [];
        $media     = $rawData->bnet_media     ?? [];
        $mplus     = $rawData->bnet_mplus     ?? [];
        $raid      = $rawData->bnet_raid      ?? [];
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

        // Build quest lookup set once for quest/prey checks
        $completedQuestIds = $this->progression->buildCompletedQuestSet($quests);

        // Parse achievement stats for raid/dungeon/delve weekly tracking
        $achStatsIndex = $this->progression->indexAchievementStatistics($achStats);

        // Prey and quest data
        $preyWeekly = $this->progression->resolvePreyWeekly($completedQuestIds);
        $weeklyQuests = $this->progression->resolveWeeklyQuests($completedQuestIds);

        // Vault
        $weekRegularMythic = $this->vaultData->resolveWeekRegularMythicDungeons($achStatsIndex);

        return new CompiledRosterMemberDTO(
            // Profile
            avatar_url:             $this->resolveAvatarUrl($media),
            class:                  $this->resolveClass($profile),
            combat_role:            $this->resolveRole($profile),
            equipped_ilvl:          $this->resolveEquippedIlvl($profile),
            highest_ilvl_ever:      null, // Requires historical tracking (future)
            // M+
            mythic_rating:          $this->instanceData->resolveMythicRating($mplus),
            weekly_runs_count:      $this->instanceData->resolveWeeklyRunsCount($mplus, $rio),
            week_regular_mythic:    $weekRegularMythic,
            season_heroic_dungeons: $this->instanceData->resolveSeasonHeroicDungeons($achStatsIndex),
            // Gear audit
            missing_enchants_slots: $this->gearAudit->resolveMissingEnchants($equippedItems),
            empty_sockets_count:    $this->gearAudit->resolveEmptySockets($equippedItems),
            upgrades_missing:       $this->gearAudit->resolveTotalUpgradesMissing($equippedItems),
            sparks_equipped:        $this->gearAudit->resolveSparksEquipped($equippedItems),
            tier_pieces:            $this->gearAudit->resolveTierPieces($equippedItems),
            tier_ilvls:             $this->gearAudit->resolveTierIlvls($equippedItems),
            // Raids
            raids:                  $this->instanceData->resolveRaids($raid),
            // Equipment
            equipment:              $this->gearAudit->resolveEquipment($equippedItems, $rioItems, $rawData),
            // Vault
            vault_weekly_runs:      $this->vaultData->resolveVaultWeeklyRuns($rio, $weekRegularMythic),
            vault_world_runs:       $this->vaultData->resolveVaultWorldRuns($achStats, $snapshot, $weeklyQuests, $preyWeekly),
            vault_raid_slots:       $this->vaultData->resolveVaultRaidSlots($achStatsIndex),
            // Quests & Delves
            prey_weekly:            $preyWeekly,
            weekly_quests:          $weeklyQuests,
            weekly_event_done:      $this->progression->resolveWeeklyEventDone($completedQuestIds),
            season_delves:          $this->progression->resolveSeasonDelves($achStatsIndex),
            week_delves:            $this->progression->resolveWeekDelves($achStats, $snapshot),
            coffer_keys:            $this->progression->resolveCofferKeys($achStatsIndex),
            // Achievements
            cutting_edge:           $this->progression->resolveCuttingEdge($achStats),
            ahead_of_the_curve:     $this->progression->resolveAheadOfTheCurve($achStats),
            // Collections
            achievement_points:     (int) ($profile['achievement_points'] ?? 0),
            crests:                 $this->progression->resolveCrests($achStatsIndex),
            mounts_count:           count($mounts['mounts'] ?? []),
            unique_pets:            $this->collection->resolveUniquePets($pets),
            lvl_25_pets:            $this->collection->resolveLvl25Pets($pets),
            titles_count:           count($titles['titles'] ?? []),
            // PvP
            honor_level:            (int) ($pvpSum['honor_level'] ?? 0),
            honorable_kills:        (int) ($pvpSum['honorable_kills'] ?? 0),
            pvp_brackets:           $this->collection->resolvePvpBrackets($pvpSum),
            // Reputation
            renown:                 $this->collection->resolveRenown($reps),
            // Crafting extras
            embellished_items:      $this->gearAudit->resolveEmbellishedItems($equippedItems),
            spark_gear:             $this->gearAudit->resolveSparkGear($equippedItems),
        );
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
