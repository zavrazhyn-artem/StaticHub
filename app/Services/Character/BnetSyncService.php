<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Exceptions\JsonSchemaValidationException;
use App\Helpers\WeeklyResetHelper;
use App\Models\Character;
use App\Services\Blizzard\BlizzardCharacterApiService;
use App\Services\Gear\EquippedGearSyncService;
use App\Services\JsonSchemaValidatorService;
use App\Services\Roster\RosterCompilerService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Self-contained Blizzard sync: fetch every Bnet endpoint we care about for
 * one character, update the accumulator columns on `characters`
 * (bnet_equipment_by_spec, vault_weekly_snapshot), then atomically merge the
 * compiled bnet slice into character_data / character_weekly_data.
 *
 * Replaces the old RawDataSyncService → CompileCharacterDataJob hand-off.
 * Now Fetch and Compile happen inline so we don't need services_raw_data as
 * a transit table — raw payloads are GC'd at end of method.
 */
final class BnetSyncService
{
    /** Hours after weekly reset during which Blizzard quest data is unreliable. */
    private const QUEST_GRACE_PERIOD_HOURS = 2;

    /** Schema-validated columns. Validation failures are logged and the field is dropped. */
    private const VALIDATED_COLUMNS = [
        'bnet_profile',
        'bnet_equipment',
        'bnet_media',
        'bnet_mplus',
        'bnet_raid',
    ];

    public function __construct(
        private readonly BlizzardCharacterApiService $blizzardApi,
        private readonly JsonSchemaValidatorService  $schemaValidator,
        private readonly RosterCompilerService       $compiler,
        private readonly EquippedGearSyncService     $gearSync,
    ) {}

    public function syncCharacter(Character $character): void
    {
        $region    = strtolower((string) ($character->realm?->region ?? 'eu'));
        $realmSlug = strtolower((string) ($character->realm?->slug ?? ''));
        $name      = mb_strtolower($character->name);

        $skip = $this->isWithinQuestGracePeriod($region) ? ['bnet_completed_quests'] : [];

        $rawByColumn = $this->blizzardApi->fetchAllCharacterEndpoints($region, $realmSlug, $name, $skip);

        $valid = [];
        foreach ($rawByColumn as $column => $payload) {
            if ($payload === null || $payload === []) {
                continue;
            }

            if (in_array($column, self::VALIDATED_COLUMNS, true)) {
                try {
                    $this->schemaValidator->validate($payload, $column);
                    $valid[$column] = $payload;
                } catch (JsonSchemaValidationException $e) {
                    Log::error("BnetSyncService: schema validation failed for {$column}.", [
                        'character_id' => $character->id,
                        'errors'       => $e->getErrorMessages(),
                    ]);
                }
            } else {
                $valid[$column] = $payload;
            }
        }

        if ($valid === []) {
            Log::warning('BnetSyncService: no valid data to persist.', ['character_id' => $character->id]);
            return;
        }

        $this->updateEquipmentBySpec($character, $valid);
        $this->updateVaultSnapshot($character, $valid['bnet_achievement_statistics'] ?? null, $region);

        // Reload accumulator state so compileAndPersistBnet sees the freshest values.
        $character->refresh();

        try {
            $this->compiler->compileAndPersistBnet($character, $valid);
        } catch (Throwable $e) {
            Log::error('BnetSyncService: compile failure.', [
                'character_id' => $character->id,
                'exception'    => $e->getMessage(),
            ]);
            throw $e;
        }

        // Refresh GearList(type=current) for the active spec — used to live in
        // SyncCurrentGearListJob → RawDataSyncService dispatch chain.
        if (!empty($valid['bnet_equipment']['equipped_items'])) {
            try {
                $this->gearSync->syncForCharacter($character, $valid['bnet_equipment']['equipped_items']);
            } catch (Throwable $e) {
                Log::warning('BnetSyncService: equipped gear list sync failed (non-fatal).', [
                    'character_id' => $character->id,
                    'exception'    => $e->getMessage(),
                ]);
            }
        }

        Log::info('BnetSyncService: persisted compiled data.', [
            'character_id' => $character->id,
            'columns'      => array_keys($valid),
        ]);
    }

    /**
     * Equipment-by-spec accumulator: Blizzard returns gear for the ACTIVE spec
     * only, so we keep a per-spec dictionary keyed by spec name and overwrite
     * the slot for the spec we just received.
     */
    private function updateEquipmentBySpec(Character $character, array $valid): void
    {
        if (!isset($valid['bnet_equipment'])) {
            return;
        }

        $specName = $this->resolveActiveSpecName($valid['bnet_profile'] ?? null, $character);
        if ($specName === null) {
            return;
        }

        $existing = $character->bnet_equipment_by_spec ?? [];
        $existing[$specName] = $valid['bnet_equipment'];

        $character->update(['bnet_equipment_by_spec' => $existing]);
    }

    /**
     * Vault snapshot accumulator: keeps an 8-week history of delve completion
     * counts so we can diff week-over-week. Skips if a snapshot already exists
     * for the current period (idempotent within a week).
     */
    private function updateVaultSnapshot(Character $character, ?array $achStats, string $region): void
    {
        if ($achStats === null) {
            return;
        }

        $periodKey   = WeeklyResetHelper::periodKey($region);
        $existingSnap = $character->vault_weekly_snapshot ?? [];

        if (isset($existingSnap[$periodKey])) {
            return;
        }

        $delveTierStatIds = config('wow_season.delve_tier_stat_ids', []);
        $delveTotalStatId = (int) config('wow_season.delve_total_stat_id', 0);

        $delveCategory = null;
        foreach ($achStats['categories'] ?? [] as $category) {
            if (($category['name'] ?? '') === 'Delves') {
                $delveCategory = $category;
                break;
            }
        }

        if ($delveCategory === null) {
            return;
        }

        $statsById = [];
        foreach ($delveCategory['statistics'] ?? [] as $stat) {
            $statsById[(int) $stat['id']] = (int) ($stat['quantity'] ?? 0);
        }

        $snapshot = ['total' => $statsById[$delveTotalStatId] ?? 0];
        foreach ($delveTierStatIds as $tier => $statId) {
            $snapshot["tier_{$tier}"] = $statsById[$statId] ?? 0;
        }

        $existingSnap[$periodKey] = $snapshot;

        // Prune entries older than 8 weeks so the column doesn't grow unbounded.
        $eightWeeksAgo = strtotime('-8 weeks');
        foreach (array_keys($existingSnap) as $key) {
            $keyTimestamp = strtotime($key);
            if ($keyTimestamp !== false && $keyTimestamp < $eightWeeksAgo) {
                unset($existingSnap[$key]);
            }
        }

        $character->update(['vault_weekly_snapshot' => $existingSnap]);
    }

    private function isWithinQuestGracePeriod(string $region): bool
    {
        $resetTs = WeeklyResetHelper::resetTimestamp($region);

        return time() < $resetTs + (self::QUEST_GRACE_PERIOD_HOURS * 3600);
    }

    private function resolveActiveSpecName(?array $bnetProfile, Character $character): ?string
    {
        if ($bnetProfile !== null) {
            $specName = is_array($bnetProfile['active_spec'] ?? null)
                ? ($bnetProfile['active_spec']['name'] ?? null)
                : ($bnetProfile['active_spec'] ?? null);

            if ($specName === null) {
                $specName = is_array($bnetProfile['active_specialization'] ?? null)
                    ? ($bnetProfile['active_specialization']['name'] ?? null)
                    : ($bnetProfile['active_specialization'] ?? null);
            }

            if ($specName !== null && $specName !== '') {
                return $specName;
            }
        }

        $activeSpec = $character->active_spec;

        return ($activeSpec !== null && $activeSpec !== '') ? $activeSpec : null;
    }
}
