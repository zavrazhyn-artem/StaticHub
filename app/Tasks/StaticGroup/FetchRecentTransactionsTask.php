<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\Transaction;
use Illuminate\Support\Collection;

class FetchRecentTransactionsTask
{
    public function run(int $staticId, int $limit = 10): Collection
    {
        return Transaction::query()
            ->forStatic($staticId)
            ->with('user')
            ->recent($limit)
            ->get();
    }
}
