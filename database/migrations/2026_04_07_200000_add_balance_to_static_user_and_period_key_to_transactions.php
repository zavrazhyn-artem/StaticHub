<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add balance and tax covered to static_user pivot
        Schema::table('static_user', function (Blueprint $table) {
            $table->bigInteger('balance')->default(0)->after('roster_status');
            $table->boolean('current_weekly_tax_covered')->default(false)->after('balance');
        });

        // 2. Add period_key to transactions, migrate data, drop week_number
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('period_key', 10)->nullable()->after('type');
        });

        // Convert existing week_number → period_key (approximate: year-Wxx)
        DB::table('transactions')->whereNotNull('week_number')->orderBy('id')->each(function ($tx) {
            $year = date('o', strtotime($tx->created_at ?? 'now'));
            $week = str_pad((string) $tx->week_number, 2, '0', STR_PAD_LEFT);
            DB::table('transactions')->where('id', $tx->id)->update([
                'period_key' => "{$year}-W{$week}",
            ]);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('week_number');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('week_number')->default(0)->after('type');
        });

        DB::table('transactions')->whereNotNull('period_key')->each(function ($tx) {
            $parts = explode('-W', $tx->period_key);
            $week = (int) ($parts[1] ?? 0);
            DB::table('transactions')->where('id', $tx->id)->update(['week_number' => $week]);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('period_key');
        });

        Schema::table('static_user', function (Blueprint $table) {
            $table->dropColumn(['balance', 'current_weekly_tax_covered']);
        });
    }
};
