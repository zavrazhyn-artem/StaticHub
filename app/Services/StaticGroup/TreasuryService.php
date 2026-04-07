<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Helpers\CurrencyHelper;
use App\Models\StaticGroup;
use App\Models\Transaction;
use App\Services\StaticGroup\ConsumableService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        private readonly ConsumableService $consumableService,
    ) {}

    public function buildTreasuryIndexPayload(StaticGroup $static): array
    {
        $consumablesData = $this->consumableService->buildConsumablesPayload($static);
        $weeklyCost = $consumablesData['grand_total_weekly_cost'] ?? 0;
        $fixedTax = (int) ($static->weekly_tax_per_player ?? 0);

        $reserves = $this->fetchTotalReserves($static);
        $recentTransactions = $this->fetchRecentTransactions($static, 5);
        $weeklyStatus = $this->fetchWeeklyTaxStatus($static, $fixedTax);

        $autonomy = CurrencyHelper::calculateAutonomy($reserves, $weeklyCost);

        $realCostPerPlayer = (int) ceil($weeklyCost / 20);
        $warning = $this->computeTaxWarning($fixedTax, $realCostPerPlayer);

        return array_merge([
            'static'            => $static,
            'reserves'          => $reserves,
            'recentTransactions' => $recentTransactions,
            'weeklyStatus'      => $weeklyStatus,
            'weeklyCost'        => $weeklyCost,
            'autonomy'          => $autonomy,
            'targetTax'         => $fixedTax,
        ], $warning, $consumablesData);
    }

    public function fetchWeeklyTaxStatus(StaticGroup $static, ?int $targetTax = null, ?int $weekNumber = null): Collection
    {
        $weekNumber = $weekNumber ?? Carbon::now()->weekOfYear;
        $taxAmount = (int) ($targetTax ?? $static->guild_tax_per_player);

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
                'total_paid' => (int) $paid,
                'is_paid' => $taxAmount > 0 ? $paid >= $taxAmount : true,
            ];
        });
    }

    public function fetchTotalReserves(StaticGroup $static): int
    {
        return $this->calculateReserves($static->id);
    }

    /**
     * Calculate the total treasury reserves for a static group.
     *
     * @param int $staticId
     * @return int
     */
    public function calculateReserves(int $staticId): int
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

    /**
     * Create a new treasury transaction.
     *
     * @param int $staticId
     * @param array $data
     * @return Transaction
     */
    public function createTransaction(int $staticId, array $data): Transaction
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

    public function executeTransactionCreation(StaticGroup $static, array $data): Transaction
    {
        return $this->createTransaction($static->id, $data);
    }

    /**
     * Fetch recent transactions for a static group.
     *
     * @param int $staticId
     * @param int $limit
     * @return Collection
     */
    public function getRecentTransactions(int $staticId, int $limit = 10): Collection
    {
        return Transaction::query()
            ->forStatic($staticId)
            ->with('user')
            ->recent($limit)
            ->get();
    }

    public function fetchRecentTransactions(StaticGroup $static, int $limit = 10): Collection
    {
        return $this->getRecentTransactions($static->id, $limit);
    }

    public function getPaginatedTransactions(int $staticId, ?int $userId = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->forStatic($staticId)
            ->with('user')
            ->latestFirst();

        if ($userId) {
            $query->forUser($userId);
        }

        return $query->paginate($perPage);
    }

    public function buildTransactionHistoryPayload(StaticGroup $static, ?int $userId = null): array
    {
        $transactions = $this->getPaginatedTransactions($static->id, $userId);

        $members = $static->members->map(fn ($user) => [
            'id' => (string) $user->id,
            'name' => $user->name,
        ])->values();

        return [
            'static' => $static,
            'transactions' => $transactions,
            'members' => $members,
            'selectedUserId' => $userId,
        ];
    }

    /**
     * Update a transaction's description/comment.
     */
    public function updateTransactionComment(Transaction $transaction, ?string $description): void
    {
        $transaction->update(['description' => $description]);
    }

    /**
     * Compute tax warning based on fixed tax vs real cost.
     */
    public function computeTaxWarning(int $fixedTax, int $realCostPerPlayer): array
    {
        $status      = 'success';
        $description = __('Tax covers current AH prices.');
        $cssClass    = 'text-on-surface-variant';

        if ($realCostPerPlayer > $fixedTax) {
            $status      = 'danger';
            $description = __('Deficit! Real cost is ~:amount. Increase tax.', [
                'amount' => number_format(CurrencyHelper::copperToGold($realCostPerPlayer)),
            ]);
            $cssClass = 'text-error';
        } elseif ($fixedTax > $realCostPerPlayer * 1.3) {
            $status      = 'warning';
            $description = __('High Surplus. Real cost dropped to ~:amount. Consider lowering.', [
                'amount' => number_format(CurrencyHelper::copperToGold($realCostPerPlayer)),
            ]);
            $cssClass = 'text-warning';
        }

        return [
            'taxStatus'      => $status,
            'taxDescription' => $description,
            'taxClass'       => $cssClass,
        ];
    }
}
