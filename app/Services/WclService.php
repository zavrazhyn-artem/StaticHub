<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WclService
{
    protected string $baseUrl = 'https://www.warcraftlogs.com/api/v2/client';
    protected ?string $accessToken = null;

    public function getAccessToken(): string
    {
        if ($this->accessToken) return $this->accessToken;

        $clientId = config('services.wcl.public_key');
        $clientSecret = config('services.wcl.private_key');

        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('Warcraft Logs API credentials missing.');
        }

        $token = Cache::get('wcl_access_token');
        if (is_string($token)) return $this->accessToken = $token;

        $response = Http::asForm()->post('https://www.warcraftlogs.com/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $token = $response->json('access_token');
        Cache::put('wcl_access_token', $token, 3600);

        return $this->accessToken = $token;
    }

    protected function query(string $query, array $variables = [])
    {
        $response = Http::withToken($this->getAccessToken())->post($this->baseUrl, [
            'query' => $query,
            'variables' => $variables,
        ]);

        $data = $response->json();
        if (isset($data['errors'])) throw new \Exception('WCL GraphQL Error: ' . json_encode($data['errors']));

        return $data['data'] ?? [];
    }

    public function getLogSummary(string $reportId, array $rosterNames = [])
    {
        $fightsQuery = <<<'GQL'
        query ($reportId: String!) {
          reportData {
            report(code: $reportId) {
              title
              fights { id name difficulty kill bossPercentage startTime endTime }
              masterData { actors(type: "Player") { id name subType } }
            }
          }
        }
GQL;

        $initialData = $this->query($fightsQuery, ['reportId' => $reportId])['reportData']['report'] ?? [];
        if (empty($initialData['fights'])) return ['error' => 'No data found.'];

        $raidFights = array_filter($initialData['fights'], fn($f) => in_array($f['difficulty'], [3, 4, 5]));
        if (empty($raidFights)) return ['error' => 'No raid encounters found.'];

        $fightIds = array_column($raidFights, 'id');

        // ДОДАНО viewBy: Ability для таблиці Casts, щоб обійти ліміт у 5 скілів
        $tablesQuery = <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!) {
          reportData {
            report(code: $reportId) {
              deaths: table(dataType: Deaths, fightIDs: $fightIds, killType: Encounters)
              interrupts: table(dataType: Interrupts, fightIDs: $fightIds, killType: Encounters)
              damageTaken: table(dataType: DamageTaken, fightIDs: $fightIds, killType: Encounters)
              casts: table(dataType: Casts, fightIDs: $fightIds, killType: Encounters, viewBy: Ability)
              damageDone: table(dataType: DamageDone, fightIDs: $fightIds, killType: Encounters)
              healing: table(dataType: Healing, fightIDs: $fightIds, killType: Encounters)
            }
          }
        }
GQL;

        $tablesData = $this->query($tablesQuery, ['reportId' => $reportId, 'fightIds' => array_values($fightIds)])['reportData']['report'] ?? [];

        // 1. Фільтрація РОСТЕРА
        $allActors = $initialData['masterData']['actors'] ?? [];
        $cleanPlayers = [];
        foreach ($allActors as $actor) {
            if (empty($rosterNames) || in_array($actor['name'], $rosterNames)) {
                $cleanPlayers[] = $actor;
            }
        }

        // 2. Очищення СМЕРТЕЙ
        $cleanDeaths = array_map(fn($d) => [
            'player' => $d['name'] ?? 'Unknown',
            'fight_id' => $d['fight'] ?? null,
            'killing_blow' => $d['killingBlow']['name'] ?? 'Unknown Ability'
        ], $tablesData['deaths']['data']['entries'] ?? []);

        // 3. Очищення КІКІВ
        $interruptEntries = $tablesData['interrupts']['data']['entries'][0]['entries'] ?? [];
        $cleanInterrupts = array_map(function ($int) {
            $interrupters = [];
            foreach ($int['details'] ?? [] as $detail) $interrupters[$detail['name']] = $detail['total'];
            return [
                'enemy_ability' => $int['name'] ?? 'Unknown',
                'total_interrupted' => $int['spellsInterrupted'] ?? 0,
                'total_missed' => $int['spellsCompleted'] ?? 0,
                'interrupted_by' => $interrupters,
            ];
        }, $interruptEntries);

        // 4. Очищення УРОНУ
        $damageEntries = $tablesData['damageTaken']['data']['entries'] ?? [];
        $abilityDamageMap = [];

        foreach ($damageEntries as $playerData) {
            if (($playerData['type'] ?? '') === 'NPC') continue;
            $playerName = $playerData['name'] ?? 'Unknown';

            foreach ($playerData['abilities'] ?? [] as $ability) {
                $abilityName = $ability['name'] ?? 'Unknown';
                if (in_array($abilityName, ['Melee', 'Attack', 'Auto Attack', 'Stagger', 'Burning Rush'])) continue;

                if (!isset($abilityDamageMap[$abilityName])) {
                    $abilityDamageMap[$abilityName] = ['total_damage_to_raid' => 0, 'victims' => []];
                }
                $abilityDamageMap[$abilityName]['total_damage_to_raid'] += $ability['total'] ?? 0;
                $abilityDamageMap[$abilityName]['victims'][$playerName] = $ability['total'] ?? 0;
            }
        }

        $cleanDamageTaken = [];
        foreach ($abilityDamageMap as $abilityName => $data) {
            arsort($data['victims']);
            $cleanDamageTaken[] = [
                'ability' => $abilityName,
                'total_damage_to_raid' => $data['total_damage_to_raid'],
                'biggest_victims' => array_slice($data['victims'], 0, 3, true)
            ];
        }

        // 5. НОВЕ ОЧИЩЕННЯ КАСTІВ (Без лімітів, завдяки viewBy: Ability)
        $castEntries = $tablesData['casts']['data']['entries'] ?? [];
        $castsSummary = [];
        $cleanConsumables = [];
        $ignoredAbilities = ['Melee', 'Auto Attack', 'Shoot', 'Wand', 'Attack'];

        foreach ($castEntries as $abilityData) {
            $abilityName = $abilityData['name'] ?? 'Unknown';
            if (in_array($abilityName, $ignoredAbilities)) continue;

            // З viewBy: Ability гравці зазвичай лежать у 'entries', 'details' або 'sources'
            $actors = $abilityData['entries'] ?? $abilityData['details'] ?? $abilityData['sources'] ?? [];

            foreach ($actors as $actor) {
                $playerName = $actor['name'] ?? 'Unknown';
                $totalCasts = $actor['total'] ?? 0;

                // Перевірка ростера
                if (!empty($rosterNames) && !in_array($playerName, $rosterNames)) continue;

                // 5a. Витратні матеріали
                $isPotion = stripos($abilityName, 'Potion') !== false;
                $isHealthstone = $abilityName === 'Healthstone';

                if ($isPotion || $isHealthstone) {
                    if (!isset($cleanConsumables[$playerName])) {
                        $cleanConsumables[$playerName] = [];
                    }
                    $cleanConsumables[$playerName][$abilityName] = ($cleanConsumables[$playerName][$abilityName] ?? 0) + $totalCasts;
                }

                // 5b. Загальна статистика кастів
                if (!isset($castsSummary[$playerName])) {
                    $castsSummary[$playerName] = [];
                }
                if (!isset($castsSummary[$playerName][$abilityName])) {
                    $castsSummary[$playerName][$abilityName] = 0;
                }
                $castsSummary[$playerName][$abilityName] += $totalCasts;
            }
        }

        // 6. Очищення МЕТРИК ЕФЕКТИВНОСТІ
        $performanceMetrics = [];
        $damageDoneEntries = $tablesData['damageDone']['data']['entries'] ?? [];
        $healingEntries = $tablesData['healing']['data']['entries'] ?? [];
        $raidDuration = max(1, $this->getTotalRaidDuration($raidFights));

        // Parse DPS
        $dpsList = [];
        foreach ($damageDoneEntries as $entry) {
            if (($entry['type'] ?? '') === 'NPC') continue;
            if (!empty($rosterNames) && !in_array($entry['name'], $rosterNames)) continue;

            $dpsList[] = ['name' => $entry['name'], 'total' => $entry['total']];
            $performanceMetrics[$entry['name']] = [
                'dps' => round($entry['total'] / $raidDuration), // Тепер рахуватиме нормально
                'dps_rank' => 0,
                'percentile' => $entry['rankPercent'] ?? null
            ];
        }

        // Rank DPS
        usort($dpsList, fn($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
        foreach ($dpsList as $index => $item) {
            if (isset($performanceMetrics[$item['name']])) $performanceMetrics[$item['name']]['dps_rank'] = $index + 1;
        }

        // Parse HPS
        $hpsList = [];
        foreach ($healingEntries as $entry) {
            if (($entry['type'] ?? '') === 'NPC') continue;
            if (!empty($rosterNames) && !in_array($entry['name'], $rosterNames)) continue;

            $hpsList[] = ['name' => $entry['name'], 'total' => $entry['total']];
            if (!isset($performanceMetrics[$entry['name']])) $performanceMetrics[$entry['name']] = [];

            $performanceMetrics[$entry['name']]['hps'] = round($entry['total'] / $raidDuration);
            $performanceMetrics[$entry['name']]['hps_rank'] = 0;
            if (!isset($performanceMetrics[$entry['name']]['percentile'])) {
                $performanceMetrics[$entry['name']]['percentile'] = $entry['rankPercent'] ?? null;
            }
        }

        // Rank HPS
        usort($hpsList, fn($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
        foreach ($hpsList as $index => $item) {
            if (isset($performanceMetrics[$item['name']])) $performanceMetrics[$item['name']]['hps_rank'] = $index + 1;
        }

        usort($cleanDamageTaken, fn($a, $b) => $b['total_damage_to_raid'] <=> $a['total_damage_to_raid'] ?? 0);

        return [
            'raid_title' => $initialData['title'] ?? 'Unknown Raid',
            'players' => $cleanPlayers,
            'deaths' => $cleanDeaths,
            'interrupts' => $cleanInterrupts,
            'major_damage_taken' => array_slice($cleanDamageTaken, 0, 15),
            'casts_summary' => $castsSummary,
            'consumables_used' => $cleanConsumables,
            'performance_metrics' => $performanceMetrics
        ];
    }

    protected function getTotalRaidDuration(array $fights): int
    {
        $duration = 0;
        foreach ($fights as $fight) {
            // ВИПРАВЛЕНО: GraphQL повертає ключі у camelCase (startTime, endTime)
            $duration += ($fight['endTime'] ?? 0) - ($fight['startTime'] ?? 0);
        }
        return max(1, $duration / 1000);
    }
}
