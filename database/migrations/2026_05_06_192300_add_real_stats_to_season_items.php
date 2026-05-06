<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds real (Blizzard-authoritative) stats per season item alongside the
 * liquidarmory budget-allocation `stats` already there.
 *
 * Populated by the season-items:backfill-stats command, which calls
 * /data/wow/item/{id} once per item and parses preview_item.stats[].
 *
 * Set-total panel uses these — scaled by item_level via the standard
 * 1.083^((target-base)/15) factor — so totals match what the user would see
 * on Wowhead instead of liquidarmory's allocation percentages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_items', function (Blueprint $table) {
            $table->json('real_stats')->nullable()->after('stats');
            $table->unsignedSmallInteger('base_item_level')->nullable()->after('real_stats');
        });
    }

    public function down(): void
    {
        Schema::table('season_items', function (Blueprint $table) {
            $table->dropColumn(['real_stats', 'base_item_level']);
        });
    }
};
