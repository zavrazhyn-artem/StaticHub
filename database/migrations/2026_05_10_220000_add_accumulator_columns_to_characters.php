<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds accumulator JSON columns to characters table — these are the only
 * fields from services_raw_data that needed to persist across syncs:
 *
 *   - bnet_equipment_by_spec: keyed by spec name, captures gear from each spec
 *     as the user switches specs over time. Bnet only ever returns the active
 *     spec's gear, so we accumulate.
 *
 *   - vault_weekly_snapshot: keyed by reset-period, captures delve completion
 *     statistics so we can diff week-over-week. Pruned to last 8 weeks.
 *
 * Other JSON columns on services_raw_data (bnet_profile, bnet_equipment, rio_profile, etc.)
 * are transient — fetched, compiled into character_data, then discarded. No
 * accumulator semantics, so they don't need persistent storage anywhere.
 *
 * Backfill copies existing accumulator state from services_raw_data so the
 * cutover loses nothing. After downstream code stops writing to services_raw_data,
 * a follow-up migration drops that table entirely.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->json('bnet_equipment_by_spec')->nullable()->after('character_weekly_data');
            $table->json('vault_weekly_snapshot')->nullable()->after('bnet_equipment_by_spec');
        });

        // Backfill from services_raw_data if it still exists. Skip silently when the
        // table is already gone (fresh installs / future re-runs after the drop).
        if (Schema::hasTable('services_raw_data')) {
            DB::statement("
                UPDATE characters c
                INNER JOIN services_raw_data s ON s.character_id = c.id
                SET c.bnet_equipment_by_spec = s.bnet_equipment_by_spec,
                    c.vault_weekly_snapshot = s.vault_weekly_snapshot
            ");
        }
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['bnet_equipment_by_spec', 'vault_weekly_snapshot']);
        });
    }
};
