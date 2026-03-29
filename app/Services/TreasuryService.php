<?php

namespace App\Services;

use App\Models\StaticGroup;
use App\Models\Transaction;
use Carbon\Carbon;
use App\Helpers\CurrencyHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function getWeeklyStatus(StaticGroup $static, int $targetTax = null, int $weekNumber = null): Collection
    {
        $weekNumber = $weekNumber ?? Carbon::now()->weekOfYear;
        $taxAmount = $targetTax ?? $static->guild_tax_per_player;

        $payments = Transaction::where('static_id', $static->id)
            ->where('week_number', $weekNumber)
            ->where('type', 'deposit')
            ->select('user_id', DB::raw('SUM(amount) as total_paid'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return $static->members->map(function ($user) use ($payments, $taxAmount) {
            $paid = $payments->get($user->id)->total_paid ?? 0;
            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'total_paid' => $paid,
                'is_paid' => $taxAmount > 0 ? $paid >= $taxAmount : true,
            ];
        });
    }

    public function getTotalReserves(StaticGroup $static): int
    {
        $deposits = Transaction::where('static_id', $static->id)
            ->where('type', 'deposit')
            ->sum('amount');

        $withdrawals = Transaction::where('static_id', $static->id)
            ->where('type', 'withdrawal')
            ->sum('amount');

        return $deposits - $withdrawals;
    }

    public function getWeeksOfAutonomy(StaticGroup $static, int $weeklyCost): float
    {
        $reserves = $this->getTotalReserves($static);
        return CurrencyHelper::calculateAutonomy($reserves, $weeklyCost);
    }

    public function addTransaction(StaticGroup $static, array $data): Transaction
    {
        return Transaction::create([
            'static_id' => $static->id,
            'user_id' => $data['user_id'],
            'amount' => $data['amount'],
            'type' => $data['type'] ?? 'deposit',
            'week_number' => $data['week_number'] ?? Carbon::now()->weekOfYear,
            'description' => $data['description'] ?? null,
            'created_at' => Carbon::now(),
        ]);
    }

    public function getRecentTransactions(StaticGroup $static, int $limit = 10): Collection
    {
        return Transaction::where('static_id', $static->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
