<?php

declare(strict_types=1);

namespace App\Tasks\Item;

use Illuminate\Support\Facades\DB;

class FetchIncompleteItemIdsTask
{
    /**
     * Fetch IDs of items that need their metadata synced.
     *
     * @return array<int>
     */
    public function run(): array
    {
        return DB::table('items')
            ->where('name', 'like', 'Item #%')
            ->orWhereNull('icon')
            ->pluck('id')
            ->toArray();
    }
}
