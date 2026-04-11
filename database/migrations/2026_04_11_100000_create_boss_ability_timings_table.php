<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boss_ability_timings', function (Blueprint $table) {
            $table->id();
            $table->string('season', 40);
            $table->string('encounter_slug');
            $table->unsignedBigInteger('spell_id');
            $table->string('name');
            $table->string('icon_filename')->nullable();
            $table->string('color', 9)->default('#FFFFFF');
            $table->string('ability_type', 40)->nullable();
            $table->json('default_casts');
            $table->unsignedInteger('duration_sec')->default(0);
            $table->unsignedInteger('row_order')->default(0);
            $table->timestamps();

            $table->unique(['season', 'encounter_slug', 'spell_id'], 'bat_season_encounter_spell_unique');
            $table->index(['season', 'encounter_slug'], 'bat_season_encounter_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boss_ability_timings');
    }
};
