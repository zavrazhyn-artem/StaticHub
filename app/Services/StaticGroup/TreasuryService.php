<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\StaticGroup;
use App\Models\Transaction;
use App\Services\ConsumableService;
use App\Tasks\StaticGroup\CalculateTreasuryReservesTask;
use App\Tasks\StaticGroup\CreateTreasuryTransactionTask;
use App\Tasks\StaticGroup\FetchRecentTransactionsTask;
use App\Tasks\StaticGroup\FetchWeeklyTaxStatusTask;
use Carbon\Carbon;
use App\Helpers\CurrencyHelper;
use Illuminate\Support\Collection;

class TreasuryService
{
    public function __construct(
        private readonly ConsumableService $consumableService,
        private readonly CalculateTreasuryReservesTask $calculateTreasuryReservesTask,
        private readonly FetchWeeklyTaxStatusTask $fetchWeeklyTaxStatusTask,
        private readonly FetchRecentTransactionsTask $fetchRecentTransactionsTask,
        private readonly CreateTreasuryTransactionTask $createTreasuryTransactionTask,
    ) {}

    public function buildTreasuryIndexPayload(StaticGroup $static): array
    {
        $consumablesData = $this->consumableService->buildConsumablesPayload($static);
        $weeklyCost = $consumablesData['grand_total_weekly_cost'] ?? 0;
        $fixedTax = (int) ($static->weekly_tax_per_player ?? 0);

        $reserves = $this->fetchTotalReserves($static);
        $recentTransactions = $this->fetchRecentTransactions($static);
        $weeklyStatus = $this->fetchWeeklyTaxStatus($static, $fixedTax);

        $autonomy = CurrencyHelper::calculateAutonomy($reserves, $weeklyCost);

        $realCostPerPlayer = $weeklyCost / 20;

        $taxStatus = 'success';
        $taxDescription = '✅ Tax covers current AH prices.';
        $taxIcon = 'heroicon-m-check-circle';

        if ($realCostPerPlayer > $fixedTax) {
            $taxStatus = 'danger';
            $taxDescription = '⚠️ Deficit! Real cost is ~' . number_format(CurrencyHelper::copperToGold((int) $realCostPerPlayer)) . '. Increase tax.';
            $taxIcon = 'heroicon-m-arrow-trending-up';
        } elseif ($fixedTax > $realCostPerPlayer * 1.3) {
            $taxStatus = 'warning';
            $taxDescription = '📉 High Surplus. Real cost dropped to ~' . number_format(CurrencyHelper::copperToGold((int) $realCostPerPlayer)) . '. Consider lowering.';
            $taxIcon = 'heroicon-m-arrow-trending-down';
        }

        return array_merge([
            'static' => $static,
            'reserves' => $reserves,
            'recentTransactions' => $recentTransactions,
            'weeklyStatus' => $weeklyStatus,
            'weeklyCost' => $weeklyCost,
            'autonomy' => $autonomy,
            'targetTax' => $fixedTax,
            'taxStatus' => $taxStatus,
            'taxDescription' => $taxDescription,
            'taxIcon' => $taxIcon,
        ], $consumablesData);
    }

    public function fetchWeeklyTaxStatus(StaticGroup $static, ?int $targetTax = null, ?int $weekNumber = null): Collection
    {
        $weekNumber = $weekNumber ?? Carbon::now()->weekOfYear;
        $taxAmount = $targetTax ?? $static->guild_tax_per_player;

        return $this->fetchWeeklyTaxStatusTask->run($static, (int) $taxAmount, $weekNumber);
    }

    public function fetchTotalReserves(StaticGroup $static): int
    {
        return $this->calculateTreasuryReservesTask->run($static->id);
    }

    public function executeTransactionCreation(StaticGroup $static, array $data): Transaction
    {
        return $this->createTreasuryTransactionTask->run($static->id, $data);
    }

    public function fetchRecentTransactions(StaticGroup $static, int $limit = 10): Collection
    {
        return $this->fetchRecentTransactionsTask->run($static->id, $limit);
    }
}
