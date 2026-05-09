<?php

declare(strict_types=1);

namespace App\Services\Wishlist;

use App\Exceptions\WishlistImportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a Raidbots Droptimizer report and normalizes it into a DTO array.
 *
 * Output shape:
 * [
 *   'character'        => ['name', 'realm', 'region', 'class_slug', 'spec_slug'],
 *   'baseline_dps'     => float,
 *   'generated_at'     => Carbon,
 *   'source_report_id' => string,
 *   'source_url'       => string,
 *   'public_title'     => string,
 *   'raw_payload'      => array (the full JSON, kept for re-parsing if schema shifts),
 *   'simulations'      => [
 *     [
 *       'raid_slug'  => 'instance-{id}',
 *       'difficulty' => mythic|heroic|normal|raid_finder,
 *       'items'      => [
 *         ['item_id', 'slot', 'item_level', 'enchant_id', 'encounter_id',
 *          'value' (int dps gain), 'percent' (float)],
 *         ...
 *       ],
 *     ],
 *     ...
 *   ],
 * ]
 */
class RaidbotsImportService
{
    private const DIFFICULTY_MAP = [
        'raid-mythic'  => 'mythic',
        'raid-heroic'  => 'heroic',
        'raid-normal'  => 'normal',
        'raid-lfr'     => 'raid_finder',
    ];

    /**
     * Trinket1/2 + finger1/2 are positional in Raidbots but represent the same
     * inventory slot in WoW. We collapse them so items.slot stays canonical.
     */
    private const SLOT_NORMALIZE = [
        'trinket1' => 'trinket',
        'trinket2' => 'trinket',
        'finger1'  => 'finger',
        'finger2'  => 'finger',
    ];

    public function isOwnUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && str_contains($host, 'raidbots.com');
    }

    public function importFromUrl(string $url): array
    {
        $reportId = $this->extractReportId($url);
        $payload  = $this->fetchPayload($reportId);

        return $this->parsePayload($payload, $url);
    }

    private function extractReportId(string $url): string
    {
        if (! preg_match('#raidbots\.com/(?:simbot/report|reports)/([a-zA-Z0-9_-]+)#', $url, $m)) {
            throw WishlistImportException::invalidPayload("URL has no recognizable Raidbots report ID: {$url}");
        }

        return $m[1];
    }

    private function fetchPayload(string $reportId): array
    {
        $endpoint = "https://www.raidbots.com/reports/{$reportId}/data.json";

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'BlastrBot/0.1 (+https://blastr.pro)',
                'Accept'     => 'application/json',
            ])
                ->timeout(30)
                ->retry(3, 500, function ($exception, $request) {
                    return $exception instanceof ConnectionException
                        || ($exception->response?->status() ?? 0) >= 500
                        || ($exception->response?->status() ?? 0) === 429;
                }, throw: false)
                ->get($endpoint);
        } catch (ConnectionException $e) {
            throw WishlistImportException::fetchFailed($endpoint, 0);
        }

        if (! $response->ok()) {
            throw WishlistImportException::fetchFailed($endpoint, $response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw WishlistImportException::invalidPayload('response body is not JSON');
        }

        return $payload;
    }

    private function parsePayload(array $payload, string $url): array
    {
        $simbot = $payload['simbot'] ?? null;
        if (! is_array($simbot)) {
            throw WishlistImportException::invalidPayload('missing simbot block');
        }

        $simType = $simbot['simType'] ?? null;
        if ($simType !== 'droptimizer') {
            throw WishlistImportException::unsupportedSimType($simType ?? 'unknown');
        }

        $playerName = $simbot['player'] ?? null;
        $charClass  = $simbot['charClass'] ?? null;
        $specSlug   = $simbot['spec'] ?? null;
        if (! $playerName || ! $charClass || ! $specSlug) {
            throw WishlistImportException::invalidPayload('player/charClass/spec missing in simbot');
        }

        $armory = $simbot['meta']['rawFormData']['armory'] ?? [];
        $realm  = $armory['realm'] ?? str_replace('_', '-', $simbot['meta']['rawFormData']['character']['realm'] ?? '');
        $region = $armory['region'] ?? $simbot['meta']['rawFormData']['character']['region'] ?? null;
        if (! $realm) {
            throw WishlistImportException::invalidPayload('realm missing in armory/character block');
        }

        $baselineDps = $payload['sim']['players'][0]['collected_data']['dps']['mean'] ?? null;
        if (! is_numeric($baselineDps) || $baselineDps <= 0) {
            throw WishlistImportException::invalidPayload('baseline DPS missing or zero');
        }
        $baselineDps = (float) $baselineDps;

        $results = $payload['sim']['profilesets']['results'] ?? [];
        if (! is_array($results) || empty($results)) {
            throw WishlistImportException::invalidPayload('no profileset results found');
        }

        $generatedAt    = Carbon::createFromTimestamp((int) ($payload['timestamp'] ?? time()));
        $sourceReportId = (string) ($simbot['simId'] ?? '');
        $publicTitle    = (string) ($simbot['publicTitle'] ?? $simbot['title'] ?? '');

        $simulations = $this->groupResultsBySimulation($results, $baselineDps);

        return [
            'character' => [
                'name'       => (string) $playerName,
                'realm'      => (string) $realm,
                'region'     => $region ? (string) $region : null,
                'class_slug' => (string) $charClass,
                'spec_slug'  => (string) $specSlug,
            ],
            'baseline_dps'     => $baselineDps,
            'generated_at'     => $generatedAt,
            'source_report_id' => $sourceReportId,
            'source_url'       => $url,
            'public_title'     => $publicTitle,
            'raw_payload'      => $payload,
            'simulations'      => $simulations,
        ];
    }

    /**
     * Groups profileset results by (raid_instance_id, difficulty) tuple and
     * computes upgrade value/percent vs baseline. Skips results whose name
     * doesn't match the expected schema instead of throwing — Raidbots
     * occasionally emits aux profilesets we don't care about.
     *
     * @return array<int, array{raid_slug:string, difficulty:string, items:array}>
     */
    private function groupResultsBySimulation(array $results, float $baselineDps): array
    {
        $buckets = [];

        foreach ($results as $result) {
            $name = $result['name'] ?? '';
            $mean = $result['mean'] ?? null;
            if (! is_string($name) || ! is_numeric($mean)) {
                continue;
            }

            $parts = explode('/', $name);
            // expected: instance/encounter/difficulty/itemId/ilvl/enchantId/slot///
            if (count($parts) < 7) {
                continue;
            }

            $instanceId    = (int) $parts[0];
            $encounterId   = (int) $parts[1];
            $diffMarker    = $parts[2];
            $itemId        = (int) $parts[3];
            $itemLevel     = (int) $parts[4];
            $enchantId     = (int) $parts[5];
            $slotRaw       = $parts[6];

            $difficulty = self::DIFFICULTY_MAP[$diffMarker] ?? null;
            if (! $difficulty || $instanceId <= 0 || $itemId <= 0 || $slotRaw === '') {
                continue;
            }

            $slot      = self::SLOT_NORMALIZE[$slotRaw] ?? $slotRaw;
            $raidSlug  = "instance-{$instanceId}";
            $bucketKey = "{$raidSlug}|{$difficulty}";

            $valueRaw = (float) $mean - $baselineDps;
            $value    = (int) round($valueRaw);
            $percent  = round($valueRaw / $baselineDps * 100, 3);

            $buckets[$bucketKey] ??= [
                'raid_slug'  => $raidSlug,
                'difficulty' => $difficulty,
                'items'      => [],
            ];

            $buckets[$bucketKey]['items'][] = [
                'item_id'      => $itemId,
                'slot'         => $slot,
                'item_level'   => $itemLevel,
                'enchant_id'   => $enchantId,
                'encounter_id' => $encounterId,
                'value'        => $value,
                'percent'      => $percent,
            ];
        }

        if (empty($buckets)) {
            Log::warning('Raidbots payload had results but none matched schema', ['count' => count($results)]);
            throw WishlistImportException::invalidPayload('no profileset results matched the expected name schema');
        }

        return array_values($buckets);
    }
}
