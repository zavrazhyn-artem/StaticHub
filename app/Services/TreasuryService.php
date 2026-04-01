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
    public function __construct(
        private readonly ConsumableService $consumableService
    ) {}

    public function getIndexData(StaticGroup $static): array
    {
        $consumablesData = $this->consumableService->getRaidConsumablesData($static);
        $weeklyCost = $consumablesData['grand_total_weekly_cost'] ?? 0;
        $calculatedTax = $consumablesData['guild_tax_per_raider'] ?? 0;

        $reserves = $this->getTotalReserves($static);
        $recentTransactions = $this->getRecentTransactions($static);
        $weeklyStatus = $this->getWeeklyStatus($static, $calculatedTax);

        $autonomy = CurrencyHelper::calculateAutonomy($reserves, $weeklyCost);

        return [
            'static' => $static,
            'reserves' => $reserves,
            'recentTransactions' => $recentTransactions,
            'weeklyStatus' => $weeklyStatus,
            'weeklyCost' => $weeklyCost,
            'autonomy' => $autonomy,
            'targetTax' => $calculatedTax,
        ];
    }

    public function getWeeklyStatus(StaticGroup $static, int $targetTax = null, int $weekNumber = null): Collection
    {
        $weekNumber = $weekNumber ?? Carbon::now()->weekOfYear;
        $taxAmount = $targetTax ?? $static->guild_tax_per_player;

        $payments = Transaction::query()
            ->forStatic($static->id)
            ->inWeek($weekNumber)
            ->byType('deposit')
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
        $deposits = Transaction::query()
            ->forStatic($static->id)
            ->byType('deposit')
            ->sumAmount();

        $withdrawals = Transaction::query()
            ->forStatic($static->id)
            ->byType('withdrawal')
            ->sumAmount();

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
        return Transaction::query()
            ->forStatic($static->id)
            ->with('user')
            ->recent($limit)
            ->get();
    }
}
