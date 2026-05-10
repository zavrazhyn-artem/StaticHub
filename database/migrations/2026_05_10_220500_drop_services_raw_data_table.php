<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the services_raw_data table now that BnetSyncService and RioSyncService
 * compile inline and persist via JSON_MERGE_PATCH onto characters.character_data.
 *
 * Accumulator state (bnet_equipment_by_spec, vault_weekly_snapshot) was already
 * backfilled into characters table by the prior migration
 * `2026_05_10_220000_add_accumulator_columns_to_characters`. Run that one first.
 *
 * Down rebuilds the table shape but does NOT restore data — there's no path back
 * to working state once the new sync code is deployed and accumulators have moved.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('services_raw_data');
    }

    public function down(): void
    {
        Schema::create('services_raw_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete()->unique();
            $table->json('bnet_profile')->nullable();
            $table->json('bnet_equipment')->nullable();
            $table->json('bnet_equipment_by_spec')->nullable();
            $table->json('bnet_statistics')->nullable();
            $table->json('bnet_media')->nullable();
            $table->json('bnet_mplus')->nullable();
            $table->json('bnet_raid')->nullable();
            $table->json('rio_profile')->nullable();
            $table->json('bnet_achievement_statistics')->nullable();
            $table->json('bnet_completed_quests')->nullable();
            $table->json('bnet_pvp_summary')->nullable();
            $table->json('bnet_reputations')->nullable();
            $table->json('bnet_titles')->nullable();
            $table->json('bnet_mounts')->nullable();
            $table->json('bnet_pets')->nullable();
            $table->json('vault_weekly_snapshot')->nullable();
            $table->timestamps();
        });
    }
};
