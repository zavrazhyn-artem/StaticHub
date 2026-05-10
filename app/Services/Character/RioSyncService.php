<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Exceptions\JsonSchemaValidationException;
use App\Models\Character;
use App\Services\JsonSchemaValidatorService;
use App\Services\Roster\RosterCompilerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Self-contained Raider.io sync: fetch the rio profile for one character and
 * atomically merge the rio-derivable fields (weekly_runs_count, vault_weekly_runs)
 * into character_weekly_data via JSON_MERGE_PATCH.
 *
 * Bnet's `week_regular_mythic` (set by the most recent BnetSyncService run)
 * is read back from character_weekly_data so vault math stays correct even
 * when bnet+rio fetches arrive in any order.
 */
final class RioSyncService
{
    private const RIO_BASE_URL = 'https://raider.io/api/v1/characters/profile';
    private const RIO_FIELDS   = 'mythic_plus_scores_by_season:current,mythic_plus_ranks,mythic_plus_recent_runs,mythic_plus_best_runs,mythic_plus_weekly_highest_level_runs,gear,talents,raid_progression,guild';

    public function __construct(
        private readonly JsonSchemaValidatorService $schemaValidator,
        private readonly RosterCompilerService      $compiler,
    ) {}

    public function syncCharacter(Character $character): void
    {
        $region    = strtolower((string) ($character->realm?->region ?? config('services.battlenet.region', 'eu')));
        $realmSlug = strtolower((string) ($character->realm?->slug ?? ''));
        $name      = mb_strtolower($character->name);

        $rio = $this->fetchRioProfile($region, $realmSlug, $name);
        if ($rio === null || $rio === []) {
            return;
        }

        try {
            $this->schemaValidator->validate($rio, 'rio_profile');
        } catch (JsonSchemaValidationException $e) {
            Log::error('RioSyncService: schema validation failed.', [
                'character_id' => $character->id,
                'errors'       => $e->getErrorMessages(),
            ]);
            return;
        }

        try {
            $this->compiler->compileAndPersistRio($character, $rio);
        } catch (Throwable $e) {
            Log::error('RioSyncService: compile failure.', [
                'character_id' => $character->id,
                'exception'    => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('RioSyncService: persisted compiled data.', [
            'character_id' => $character->id,
        ]);
    }

    private function fetchRioProfile(string $region, string $realm, string $name): ?array
    {
        $response = Http::get(self::RIO_BASE_URL, [
            'region' => $region,
            'realm'  => $realm,
            'name'   => $name,
            'fields' => self::RIO_FIELDS,
        ]);

        if ($response->failed()) {
            Log::warning('RioSyncService: Raider.io request failed.', [
                'status'    => $response->status(),
                'character' => $name,
                'realm'     => $realm,
                'region'    => $region,
            ]);
            return null;
        }

        return $response->json();
    }
}
