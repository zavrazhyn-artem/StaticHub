<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('raid_events')) {
            return;
        }

        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->dropForeign(['raid_event_id']);
        });

        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->dropForeign(['raid_event_id']);
        });

        Schema::rename('raid_events', 'events');

        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->renameColumn('raid_event_id', 'event_id');
        });

        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->renameColumn('raid_event_id', 'event_id');
        });

        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
        });

        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
        });

        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
        });

        Schema::rename('events', 'raid_events');

        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->renameColumn('event_id', 'raid_event_id');
        });

        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->renameColumn('event_id', 'raid_event_id');
        });

        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->foreign('raid_event_id')->references('id')->on('raid_events')->onDelete('cascade');
        });

        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->foreign('raid_event_id')->references('id')->on('raid_events')->onDelete('set null');
        });
    }
};
