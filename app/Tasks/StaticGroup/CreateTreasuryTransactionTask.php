<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\Transaction;
use Carbon\Carbon;

class CreateTreasuryTransactionTask
{
    public function run(int $staticId, array $data): Transaction
    {
        return Transaction::create([
            'static_id' => $staticId,
            'user_id' => $data['user_id'],
            'amount' => $data['amount'],
            'type' => $data['type'] ?? 'deposit',
            'week_number' => $data['week_number'] ?? Carbon::now()->weekOfYear,
            'description' => $data['description'] ?? null,
            'created_at' => Carbon::now(),
        ]);
    }
}
