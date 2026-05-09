<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot loot_distributions to canonical FK identifiers:
     *
     * - `recipient_character_id` (already nullable FK) is now the
     *   primary recipient identifier; `recipient_name` stays as a
     *   display fallback for awards to players who weren't in our
     *   characters table at ingest time. The backfill service
     *   re-resolves them on assignToStatic.
     * - `encounter_id` becomes the primary boss identifier; we drop
     *   the locale-dependent `boss_name` and the redundant
     *   `raid_slug` (both derivable via JOIN with season_items).
     * - `is_test_mode` separates synthesised /blastr-test rows from
     *   real raid drops so we can show them in the UI without them
     *   poisoning real per-character stats.
     */
    public function up(): void
    {
        Schema::table('loot_distributions', function (Blueprint $t) {
            $t->boolean('is_test_mode')->default(false)->after('is_award_reason');
            $t->dropColumn(['boss_name', 'raid_slug']);
        });
    }

    public function down(): void
    {
        Schema::table('loot_distributions', function (Blueprint $t) {
            $t->string('boss_name', 128)->nullable()->after('encounter_id');
            $t->string('raid_slug', 64)->nullable()->after('static_id');
            $t->dropColumn('is_test_mode');
        });
    }
};
