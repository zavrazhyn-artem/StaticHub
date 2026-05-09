<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds craftable-item support to the gear catalog and per-list picks.
 *
 * - season_items.is_craftable      → flag for the picker's "Crafted" tab
 * - season_items.secondary_pool    → which secondaries the user can pick
 *                                    (defaults to all four; some crafted
 *                                    items only allow specific subsets)
 * - gear_list_items.chosen_stats   → exactly two stats from secondary_pool
 *                                    that the user committed to when the
 *                                    item was added to the list
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('season_items', function (Blueprint $table) {
            $table->boolean('is_craftable')->default(false)->after('is_tier');
            $table->json('secondary_pool')->nullable()->after('is_craftable');
        });

        Schema::table('gear_list_items', function (Blueprint $table) {
            $table->json('chosen_stats')->nullable()->after('gem_ids');
        });
    }

    public function down(): void
    {
        Schema::table('season_items', function (Blueprint $table) {
            $table->dropColumn(['is_craftable', 'secondary_pool']);
        });

        Schema::table('gear_list_items', function (Blueprint $table) {
            $table->dropColumn('chosen_stats');
        });
    }
};
