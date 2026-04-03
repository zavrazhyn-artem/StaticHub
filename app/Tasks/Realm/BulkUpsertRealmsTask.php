<?php

declare(strict_types=1);

namespace App\Tasks\Realm;

use App\Models\Realm;

class BulkUpsertRealmsTask
{
    /**
     * Bulk upsert realms into the database.
     *
     * @param array $realmsData
     * @return int
     */
    public function run(array $realmsData): int
    {
        Realm::upsert($realmsData, ['id'], ['name', 'slug', 'region']);

        return count($realmsData);
    }
}
