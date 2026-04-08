<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Helpers\CurrencyHelper;
use App\Helpers\WeeklyResetHelper;
use App\Models\StaticGroup;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        private readonly ConsumableService $consumableService,
    ) {}

    // =========================================================================
    // PAYLOAD BUILDERS
    // =========================================================================

    public function buildTreasuryIndexPayload(StaticGroup $static, ?array $consumablesData = null): array
    {
        // Eager-load members with their main character for the member select
        $static->load(['members' => fn ($q) => $q->with([
            'characters' => fn ($q2) => $q2->whereHas('statics', fn ($q3) =>
                $q3->where('statics.id', $static->id)->where('character_static.role', 'main')
            ),
        ])]);

        $consumablesData = $consumablesData ?? $this->consumableService->buildConsumablesPayload($static);
        $weeklyCost = $consumablesData['grand_total_weekly_cost'] ?? 0;
        $fixedTax = (int) ($static->weekly_tax_per_player ?? 0);

        $reserves = $this->calculateReserves($static);
        $recentTransactions = $this->fetchRecentTransactions($static, 5);
        $weeklyStatus = $this->fetchWeeklyTaxStatus($static);

        $autonomy = CurrencyHelper::calculateAutonomy($reserves, $weeklyCost);

        $realCostPerPlayer = (int) ceil($weeklyCost / 20);
        $warning = $this->computeTaxWarning($fixedTax, $realCostPerPlayer);

        return array_merge([
            'static'             => $static,
            'reserves'           => $reserves,
            'recentTransactions' => $recentTransactions,
            'weeklyStatus'       => $weeklyStatus,
            'weeklyCost'         => $weeklyCost,
            'autonomy'           => $autonomy,
            'targetTax'          => $fixedTax,
            'membersForSelect'   => $this->formatMembersForSelect($static),
        ], $warning, $consumablesData);
    }

    public function buildTransactionHistoryPayload(StaticGroup $static, ?int $userId = null): array
    {
        // Eager-load members with main character for the select
        $static->load(['members' => fn ($q) => $q->with([
            'characters' => fn ($q2) => $q2->whereHas('statics', fn ($q3) =>
                $q3->where('statics.id', $static->id)->where('character_static.role', 'main')
            ),
        ])]);

        $transactions = $this->getPaginatedTransactions($static->id, $userId);

        return [
            'static'         => $static,
            'transactions'   => $transactions,
            'members'        => $this->formatMembersForSelect($static),
            'selectedUserId' => $userId,
        ];
    }

    // =========================================================================
    // RESERVES
    // =========================================================================

    /**
     * Reserves = sum(user balances) + tax * count(covered users) - withdrawals_this_week
     */
    public function calculateReserves(StaticGroup $static): int
    {
        $region    = strtolower($static->region ?? 'eu');
        $periodKey = WeeklyResetHelper::periodKey($region);
        $tax       = (int) ($static->weekly_tax_per_player ?? 0);

        $pivotData = DB::table('static_user')
            ->where('static_id', $static->id)
            ->select(
                DB::raw('SUM(balance) as total_balance'),
                DB::raw('SUM(CASE WHEN current_weekly_tax_covered = 1 THEN 1 ELSE 0 END) as covered_count'),
            )
            ->first();

        $totalBalance = (int) ($pivotData->total_balance ?? 0);
        $coveredCount = (int) ($pivotData->covered_count ?? 0);

        $withdrawalsThisWeek = Transaction::query()
            ->forStatic($static->id)
            ->byType('withdrawal')
            ->inPeriod($periodKey)
            ->sumAmount();

        return $totalBalance + ($tax * $coveredCount) - $withdrawalsThisWeek;
    }

    // =========================================================================
    // WEEKLY TAX STATUS
    // =========================================================================

    /**
     * Returns weekly tax status per user from the pivot bool.
     */
    public function fetchWeeklyTaxStatus(StaticGroup $static): Collection
    {
        return $static->members->map(fn ($user) => [
            'user_id'    => $user->id,
            'name'       => $user->name,
            'balance'    => (int) ($user->pivot->balance ?? 0),
            'is_paid'    => (bool) ($user->pivot->current_weekly_tax_covered ?? false),
        ]);
    }

    /**
     * Format members for the TransactionFormModal select (same shape as Transfer Ownership).
     * Returns: [{ id, name, character: { name, playable_class, avatar_url } | null }]
     */
    private function formatMembersForSelect(StaticGroup $static): array
    {
        return $static->members->map(fn ($user) => [
            'id'        => $user->id,
            'name'      => $user->name,
            'character' => ($char = $user->characters->first()) ? [
                'name'           => $char->name,
                'playable_class' => $char->playable_class,
                'avatar_url'     => $char->avatar_url,
            ] : null,
        ])->values()->toArray();
    }

    // =========================================================================
    // TRANSACTIONS
    // =========================================================================

    /**
     * Create a deposit: increases user balance, auto-covers tax if needed.
     */
    public function createDeposit(StaticGroup $static, int $userId, int $amount, ?string $description = null): Transaction
    {
        $region    = strtolower($static->region ?? 'eu');
        $periodKey = WeeklyResetHelper::periodKey($region);

        $transaction = Transaction::create([
            'static_id'  => $static->id,
            'user_id'    => $userId,
            'amount'     => $amount,
            'type'       => 'deposit',
            'period_key' => $periodKey,
            'description' => $description,
            'created_at' => Carbon::now(),
        ]);

        // Increase user balance
        DB::table('static_user')
            ->where('static_id', $static->id)
            ->where('user_id', $userId)
            ->increment('balance', $amount);

        // Auto-cover tax if not yet covered
        $this->tryCoverTax($static, $userId);

        return $transaction;
    }

    /**
     * Create a withdrawal: does NOT affect user balance, just records the transaction.
     */
    public function createWithdrawal(StaticGroup $static, int $userId, int $amount, ?string $description = null): Transaction
    {
        $region    = strtolower($static->region ?? 'eu');
        $periodKey = WeeklyResetHelper::periodKey($region);

        return Transaction::create([
            'static_id'  => $static->id,
            'user_id'    => $userId,
            'amount'     => $amount,
            'type'       => 'withdrawal',
            'period_key' => $periodKey,
            'description' => $description,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * If user hasn't covered tax yet and now has enough balance, deduct and mark covered.
     */
    public function tryCoverTax(StaticGroup $static, int $userId): void
    {
        $tax = (int) ($static->weekly_tax_per_player ?? 0);
        if ($tax <= 0) {
            return;
        }

        $pivot = DB::table('static_user')
            ->where('static_id', $static->id)
            ->where('user_id', $userId)
            ->first(['balance', 'current_weekly_tax_covered']);

        if (!$pivot || $pivot->current_weekly_tax_covered) {
            return;
        }

        if ($pivot->balance >= $tax) {
            DB::table('static_user')
                ->where('static_id', $static->id)
                ->where('user_id', $userId)
                ->update([
                    'balance' => DB::raw("balance - {$tax}"),
                    'current_weekly_tax_covered' => true,
                ]);
        }
    }

    /**
     * Process weekly tax deduction for all users in a static.
     * Called by WeeklyResetCommand.
     *
     * 1. Reset everyone to uncovered.
     * 2. If tax > 0, deduct from users who can afford it and mark them covered.
     */
    public function processWeeklyTaxReset(StaticGroup $static): void
    {
        $tax = (int) ($static->weekly_tax_per_player ?? 0);

        // Step 1: reset all members to uncovered
        DB::table('static_user')
            ->where('static_id', $static->id)
            ->update(['current_weekly_tax_covered' => false]);

        if ($tax <= 0) {
            return;
        }

        // Step 2: deduct tax from members who have enough balance
        $members = DB::table('static_user')
            ->where('static_id', $static->id)
            ->where('balance', '>=', $tax)
            ->get(['user_id']);

        foreach ($members as $member) {
            DB::table('static_user')
                ->where('static_id', $static->id)
                ->where('user_id', $member->user_id)
                ->update([
                    'balance' => DB::raw("balance - {$tax}"),
                    'current_weekly_tax_covered' => true,
                ]);
        }
    }

    public function updateTransactionComment(Transaction $transaction, ?string $description): void
    {
        $transaction->update(['description' => $description]);
    }

    public function fetchRecentTransactions(StaticGroup $static, int $limit = 10): Collection
    {
        return Transaction::query()
            ->forStatic($static->id)
            ->with('user')
            ->recent($limit)
            ->get();
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

    // =========================================================================
    // TAX WARNING
    // =========================================================================

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
