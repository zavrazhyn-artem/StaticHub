<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raid_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('static_id')->constrained('statics')->cascadeOnDelete();
            $table->string('encounter_slug');
            $table->string('difficulty', 20)->default('mythic');
            $table->string('title')->nullable();
            $table->json('steps');
            $table->timestamps();

            $table->index(['static_id', 'encounter_slug'], 'rp_static_encounter_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raid_plans');
    }
};
