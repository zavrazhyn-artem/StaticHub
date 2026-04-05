<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\JsonSchemaValidationException;
use App\Models\Character;
use App\Models\ServiceRawData;
use App\Services\BlizzardApiService;
use App\Services\JsonSchemaValidatorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches raw JSON from each upstream API route, validates it against its
 * JSON schema, and persists the valid payloads into `services_raw_data`.
 *
 * Per-route failures are isolated: a failed or invalid API response for one
 * route does not abort the remaining routes. The column is simply left
 * untouched for that sync cycle.
 */
final class SyncCharacterRawDataAction
{
    private const RIO_BASE_URL = 'https://raider.io/api/v1/characters/profile';

    private const RIO_FIELDS = 'mythic_plus_scores_by_season:current,mythic_plus_ranks,mythic_plus_recent_runs,mythic_plus_best_runs,mythic_plus_weekly_highest_level_runs,gear,talents,raid_progression,guild';

    public function __construct(
        private readonly BlizzardApiService       $blizzard,
        private readonly JsonSchemaValidatorService $schemaValidator,
    ) {}

    /**
     * Syncs API routes for the given character.
     *
     * @param string $service Which service group to fetch: 'bnet', 'rio', or 'all'.
     *                        Defaults to 'all' for backwards compatibility.
     */
    public function execute(Character $character, string $service = 'all'): void
    {
        $realmSlug = strtolower((string) ($character->realm?->slug ?? ''));
        $name      = strtolower($character->name);
        $region    = strtolower((string) ($character->realm?->region ?? config('services.battlenet.region', 'eu')));

        $updates = [];

        if ($service === 'bnet' || $service === 'all') {
            // -----------------------------------------------------------------
            // Blizzard: profile summary
            // -----------------------------------------------------------------
            $this->tryFetch(
                route:      'bnet_profile',
                schema:     'bnet_profile',
                character:  $character,
                updates:    $updates,
                fetcher:    fn() => $this->blizzard->getCharacterProfileSummary($realmSlug, $name),
            );

            // -----------------------------------------------------------------
            // Blizzard: equipment
            // -----------------------------------------------------------------
            $this->tryFetch(
                route:      'bnet_equipment',
                schema:     'bnet_equipment',
                character:  $character,
                updates:    $updates,
                fetcher:    fn() => $this->blizzard->getCharacterEquipment($region, $realmSlug, $name),
            );

            // -----------------------------------------------------------------
            // Blizzard: media (avatar)
            // -----------------------------------------------------------------
            $this->tryFetch(
                route:      'bnet_media',
                schema:     'bnet_media',
                character:  $character,
                updates:    $updates,
                fetcher:    fn() => $this->blizzard->getCharacterMedia($realmSlug, $name),
            );

            // -----------------------------------------------------------------
            // Blizzard: Mythic+ keystone profile
            // -----------------------------------------------------------------
            $this->tryFetch(
                route:      'bnet_mplus',
                schema:     'bnet_mplus',
                character:  $character,
                updates:    $updates,
                fetcher:    fn() => $this->blizzard->getCharacterMythicKeystoneProfile($realmSlug, $name),
            );

            // -----------------------------------------------------------------
            // Blizzard: raid encounters
            // -----------------------------------------------------------------
            $this->tryFetch(
                route:      'bnet_raid',
                schema:     'bnet_raid',
                character:  $character,
                updates:    $updates,
                fetcher:    fn() => $this->blizzard->getCharacterRaidEncounters($realmSlug, $name),
            );
        }

        if ($service === 'rio' || $service === 'all') {
            // -----------------------------------------------------------------
            // Raider.io: character profile
            // -----------------------------------------------------------------
            $this->tryFetch(
                route:      'rio_profile',
                schema:     'rio_profile',
                character:  $character,
                updates:    $updates,
                fetcher:    fn() => $this->fetchRioProfile($region, $realmSlug, $name),
            );
        }

        // Persist all successfully validated payloads in a single write.
        if ($updates !== []) {
            ServiceRawData::updateOrCreate(
                ['character_id' => $character->id],
                $updates,
            );

            Log::info('SyncCharacterRawDataAction: persisted raw data.', [
                'character_id' => $character->id,
                'service'      => $service,
                'routes'       => array_keys($updates),
            ]);
        } else {
            Log::warning('SyncCharacterRawDataAction: no valid data to persist.', [
                'character_id' => $character->id,
                'service'      => $service,
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * Calls $fetcher, validates the result against $schema, and adds the
     * payload to $updates on success. Logs and swallows all failures so
     * that a single broken route never aborts the full sync.
     *
     * @param  array<string,mixed> $updates  Passed by reference; populated on success.
     * @param  callable(): array<string,mixed>|null $fetcher
     */
    private function tryFetch(
        string    $route,
        string    $schema,
        Character $character,
        array     &$updates,
        callable  $fetcher,
    ): void {
        try {
            $payload = $fetcher();

            if ($payload === null || $payload === []) {
                Log::warning("SyncCharacterRawDataAction: empty response for route '{$route}'.", [
                    'character_id' => $character->id,
                ]);
                return;
            }

            $this->schemaValidator->validate($payload, $schema);

            $updates[$route] = $payload;

        } catch (JsonSchemaValidationException $e) {
            Log::error("SyncCharacterRawDataAction: schema validation failed for route '{$route}'.", [
                'character_id' => $character->id,
                'schema'       => $schema,
                'errors'       => $e->getErrorMessages(),
            ]);
        } catch (Throwable $e) {
            Log::error("SyncCharacterRawDataAction: unexpected error on route '{$route}'.", [
                'character_id' => $character->id,
                'exception'    => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Fetches the Raider.io character profile directly, since the existing
     * RaiderIoService maps the response into a DTO rather than returning raw JSON.
     *
     * @return array<string,mixed>|null
     */
    private function fetchRioProfile(string $region, string $realm, string $name): ?array
    {
        $response = Http::get(self::RIO_BASE_URL, [
            'region' => $region,
            'realm'  => $realm,
            'name'   => $name,
            'fields' => self::RIO_FIELDS,
        ]);

        if ($response->failed()) {
            Log::warning('SyncCharacterRawDataAction: Raider.io request failed.', [
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
