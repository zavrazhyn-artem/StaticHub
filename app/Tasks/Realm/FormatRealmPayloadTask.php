<?php

declare(strict_types=1);

namespace App\Tasks\Realm;

class FormatRealmPayloadTask
{
    /**
     * Format raw realm data from Blizzard API into a database-ready payload.
     *
     * @param array $rawRealms
     * @param string $region
     * @return array
     */
    public function run(array $rawRealms, string $region): array
    {
        $payload = [];

        foreach ($rawRealms as $realmData) {
            $name = $realmData['name']['en_GB'] ?? $realmData['name']['en_US'] ?? reset($realmData['name']);

            $payload[] = [
                'id' => $realmData['id'],
                'name' => $name,
                'slug' => $realmData['slug'],
                'region' => $region,
            ];
        }

        return $payload;
    }
}
