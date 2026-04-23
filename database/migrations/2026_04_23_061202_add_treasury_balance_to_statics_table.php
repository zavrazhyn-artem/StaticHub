<?php

use App\Helpers\WeeklyResetHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->bigInteger('treasury_balance')->default(0)->after('guild_tax_per_player');
        });

        // Seed each static's treasury_balance with the previously computed reserves
        // value so the displayed number does not jump on deploy.
        $statics = DB::table('statics')->select('id', 'region', 'weekly_tax_per_player')->get();

        foreach ($statics as $static) {
            $region    = strtolower($static->region ?? 'eu');
            $periodKey = WeeklyResetHelper::periodKey($region);
            $tax       = (int) ($static->weekly_tax_per_player ?? 0);

            $pivot = DB::table('static_user')
                ->where('static_id', $static->id)
                ->selectRaw('COALESCE(SUM(balance), 0) as total_balance')
                ->selectRaw('SUM(CASE WHEN current_weekly_tax_covered = 1 THEN 1 ELSE 0 END) as covered_count')
                ->first();

            $totalBalance = (int) ($pivot->total_balance ?? 0);
            $coveredCount = (int) ($pivot->covered_count ?? 0);

            $withdrawalsThisWeek = (int) DB::table('transactions')
                ->where('static_id', $static->id)
                ->where('type', 'withdrawal')
                ->where('period_key', $periodKey)
                ->sum('amount');

            $reserves = $totalBalance + ($tax * $coveredCount) - $withdrawalsThisWeek;

            DB::table('statics')
                ->where('id', $static->id)
                ->update(['treasury_balance' => $reserves]);
        }
    }

    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn('treasury_balance');
        });
    }
};
