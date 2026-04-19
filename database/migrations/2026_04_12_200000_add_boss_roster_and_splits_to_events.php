<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('boss_roster_enabled')->default(false)->after('selected_encounters');
            $table->boolean('split_enabled')->default(false)->after('boss_roster_enabled');
            $table->unsignedTinyInteger('split_count')->default(1)->after('split_enabled');
        });

        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->unsignedTinyInteger('split_group')->nullable()->after('spec_id');
        });

        Schema::table('event_encounter_rosters', function (Blueprint $table) {
            $table->unsignedTinyInteger('split_group')->nullable()->after('position_order');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['boss_roster_enabled', 'split_enabled', 'split_count']);
        });

        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->dropColumn('split_group');
        });

        Schema::table('event_encounter_rosters', function (Blueprint $table) {
            $table->dropColumn('split_group');
        });
    }
};
