<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\StaticGroup;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FetchWeeklyTaxStatusTask
{
    public function run(StaticGroup $static, int $targetTax, int $weekNumber): Collection
    {
        $payments = Transaction::query()
            ->forStatic($static->id)
            ->inWeek($weekNumber)
            ->byType('deposit')
            ->select('user_id', DB::raw('SUM(amount) as total_paid'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return $static->members->map(function ($user) use ($payments, $targetTax) {
            $paid = $payments->get($user->id)->total_paid ?? 0;
            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'total_paid' => (int) $paid,
                'is_paid' => $targetTax > 0 ? $paid >= $targetTax : true,
            ];
        });
    }
}
