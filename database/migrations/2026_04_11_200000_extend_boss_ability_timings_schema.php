<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wipe existing rows — schema is changing fundamentally and the user
        // explicitly asked for a clean reseed once the new pipeline is ready.
        DB::table('boss_ability_timings')->truncate();

        Schema::table('boss_ability_timings', function (Blueprint $table) {
            $table->dropUnique('bat_season_encounter_spell_unique');
            $table->dropIndex('bat_season_encounter_index');
        });

        Schema::table('boss_ability_timings', function (Blueprint $table) {
            $table->string('difficulty', 16)->default('mythic')->after('encounter_slug');
            $table->foreignId('static_id')->nullable()->after('difficulty')->constrained('statics')->cascadeOnDelete();
            $table->string('source_report_code', 32)->nullable()->after('row_order');
            $table->unsignedInteger('source_fight_id')->nullable()->after('source_report_code');
            $table->timestamp('source_kill_time')->nullable()->after('source_fight_id');
            $table->timestamp('seeded_at')->nullable()->after('source_kill_time');

            $table->index(['season', 'encounter_slug', 'difficulty'], 'bat_lookup_index');
            $table->index(['static_id'], 'bat_static_index');
        });
    }

    public function down(): void
    {
        Schema::table('boss_ability_timings', function (Blueprint $table) {
            $table->dropForeign(['static_id']);
            $table->dropIndex('bat_lookup_index');
            $table->dropIndex('bat_static_index');
            $table->dropColumn(['difficulty', 'static_id', 'source_report_code', 'source_fight_id', 'source_kill_time', 'seeded_at']);
            $table->unique(['season', 'encounter_slug', 'spell_id'], 'bat_season_encounter_spell_unique');
            $table->index(['season', 'encounter_slug'], 'bat_season_encounter_index');
        });
    }
};
