<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\Transaction;

class CalculateTreasuryReservesTask
{
    public function run(int $staticId): int
    {
        $deposits = Transaction::query()
            ->forStatic($staticId)
            ->byType('deposit')
            ->sumAmount();

        $withdrawals = Transaction::query()
            ->forStatic($staticId)
            ->byType('withdrawal')
            ->sumAmount();

        return $deposits - $withdrawals;
    }
}
