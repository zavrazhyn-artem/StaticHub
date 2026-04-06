<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services_raw_data', function (Blueprint $table) {
            // Achievement statistics from Blizzard API (contains delve tier counts)
            $table->json('bnet_achievement_statistics')->nullable()->after('bnet_raid');
            // Weekly snapshot: stores delve tier counts at the start of each WoW reset
            // Shape: { "period_id": { "tier_1": N, ..., "tier_11": N, "total": N } }
            $table->json('vault_weekly_snapshot')->nullable()->after('bnet_achievement_statistics');
        });
    }

    public function down(): void
    {
        Schema::table('services_raw_data', function (Blueprint $table) {
            $table->dropColumn(['bnet_achievement_statistics', 'vault_weekly_snapshot']);
        });
    }
};
