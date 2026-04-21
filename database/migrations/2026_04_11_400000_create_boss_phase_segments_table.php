<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wipe existing boss_ability_timings — we're rewriting the default_casts
        // JSON format from [seconds] to [{segment_id, offset}]. Reseed required.
        DB::table('boss_ability_timings')->truncate();

        Schema::create('boss_phase_segments', function (Blueprint $table) {
            $table->id();
            $table->string('season', 40);
            $table->string('encounter_slug');
            $table->string('difficulty', 16)->default('mythic');
            $table->foreignId('static_id')->nullable()->constrained('statics')->cascadeOnDelete();

            // segment_id is a string like "s1", "s2" — stable identifier within a
            // (season, encounter, difficulty, static) set. Used to join casts.
            $table->string('segment_id', 8);
            $table->unsignedInteger('phase_id'); // WCL phase id (can repeat for cyclic bosses)
            $table->string('phase_name');
            $table->boolean('is_intermission')->default(false);
            $table->unsignedInteger('seed_start');    // offset from fight start (seconds)
            $table->unsignedInteger('seed_duration'); // duration in the seed kill
            $table->unsignedInteger('segment_order'); // visual order

            $table->string('source_report_code', 32)->nullable();
            $table->unsignedInteger('source_fight_id')->nullable();
            $table->timestamp('seeded_at')->nullable();
            $table->timestamps();

            $table->index(['season', 'encounter_slug', 'difficulty'], 'bps_lookup_index');
            $table->index(['static_id'], 'bps_static_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boss_phase_segments');
    }
};
