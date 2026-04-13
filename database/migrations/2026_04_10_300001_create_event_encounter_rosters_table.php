<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_encounter_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('encounter_slug');
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('selection_status', 20)->default('selected');
            $table->unsignedSmallInteger('position_order')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'encounter_slug', 'character_id'], 'eer_event_encounter_character_unique');
            $table->index(['event_id', 'encounter_slug'], 'eer_event_encounter_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_encounter_rosters');
    }
};
