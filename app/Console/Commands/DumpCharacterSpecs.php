<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\Blizzard\BlizzardCharacterApiService;
use Illuminate\Console\Command;

class DumpCharacterSpecs extends Command
{
    protected $signature = 'debug:dump-specs {character : Character ID}';

    protected $description = 'Fetch and dump raw /specializations response for a character.';

    public function handle(BlizzardCharacterApiService $api): int
    {
        $character = Character::with('realm')->find((int) $this->argument('character'));

        if (!$character) {
            $this->error('Character not found.');
            return self::FAILURE;
        }

        $region    = strtolower((string) ($character->realm?->region ?? 'eu'));
        $realmSlug = strtolower((string) ($character->realm?->slug ?? ''));
        $name      = mb_strtolower($character->name);

        $this->info("Fetching /specializations for {$character->name} ({$region}/{$realmSlug})…");

        $result = $api->fetchAllCharacterEndpoints($region, $realmSlug, $name);
        $spec = $result['bnet_specialization'] ?? null;

        if ($spec === null) {
            $this->error('No specialization data returned (endpoint failed or empty).');
            return self::FAILURE;
        }

        $this->line('Top-level keys: ' . implode(', ', array_keys($spec)));
        $this->line('');
        $this->line(json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
