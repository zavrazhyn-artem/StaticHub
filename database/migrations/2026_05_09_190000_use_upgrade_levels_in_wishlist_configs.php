<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the raw `upgrade_ilvl_*` integer columns with named upgrade
 * tracks ("Myth 6/6", "Hero 5/6", …) — same picker wowaudit ships, so
 * raid leads don't have to memorise which ilvl is which crest tier.
 * The matcher resolves the friendly name → ilvl through config/wow_season.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wishlist_droptimizer_configs', function (Blueprint $table) {
            $table->string('upgrade_level_mythic', 16)->nullable()->after('require_upgrade_all_same');
            $table->string('upgrade_level_heroic', 16)->nullable()->after('upgrade_level_mythic');
            $table->string('upgrade_level_normal', 16)->nullable()->after('upgrade_level_heroic');
            $table->string('upgrade_level_lfr', 16)->nullable()->after('upgrade_level_normal');
            $table->dropColumn(['upgrade_ilvl_mythic', 'upgrade_ilvl_heroic', 'upgrade_ilvl_normal', 'upgrade_ilvl_lfr']);
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_droptimizer_configs', function (Blueprint $table) {
            $table->unsignedSmallInteger('upgrade_ilvl_mythic')->nullable()->after('require_upgrade_all_same');
            $table->unsignedSmallInteger('upgrade_ilvl_heroic')->nullable()->after('upgrade_ilvl_mythic');
            $table->unsignedSmallInteger('upgrade_ilvl_normal')->nullable()->after('upgrade_ilvl_heroic');
            $table->unsignedSmallInteger('upgrade_ilvl_lfr')->nullable()->after('upgrade_ilvl_normal');
            $table->dropColumn(['upgrade_level_mythic', 'upgrade_level_heroic', 'upgrade_level_normal', 'upgrade_level_lfr']);
        });
    }
};
