<?php

declare(strict_types=1);

namespace App\Tasks\Auction;

use Illuminate\Support\Facades\DB;

class FetchTrackedItemIdsTask
{
    /**
     * Get the item IDs we are interested in.
     */
    public function run(): array
    {
        return DB::table('items')->pluck('id')->toArray();
    }
}
