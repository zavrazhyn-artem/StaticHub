<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_cooldown_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->unsignedBigInteger('spell_id');
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->unique(['character_id', 'spell_id'], 'cco_character_spell_unique');
            $table->index('character_id', 'cco_character_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_cooldown_overrides');
    }
};
