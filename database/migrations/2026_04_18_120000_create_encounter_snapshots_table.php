<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('static_id')->constrained('statics')->onDelete('cascade');
            $table->foreignId('tactical_report_id')->nullable()->constrained('tactical_reports')->onDelete('set null');
            $table->string('boss_name');
            $table->integer('wcl_encounter_id')->nullable();
            $table->string('difficulty')->nullable();
            $table->dateTime('raid_date');
            $table->integer('duration_seconds')->default(0);
            $table->boolean('killed')->default(false);
            $table->unsignedTinyInteger('best_wipe_pct')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('total_deaths')->default(0);
            // Per-player snapshot — flat list of objects with summary metrics for the boss.
            $table->json('player_metrics');
            // Encounter-level extras (top mech failures, phase deaths, etc.)
            $table->json('encounter_summary')->nullable();
            $table->timestamps();

            $table->index(['static_id', 'boss_name', 'raid_date']);
            $table->index(['static_id', 'raid_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_snapshots');
    }
};
