<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('raid_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raid_event_id')->constrained('raid_events')->onDelete('cascade');
            $table->foreignId('character_id')->constrained('characters')->onDelete('cascade');
            $table->string('status')->default('present'); // Using string for flexibility with 'present', 'absent', 'tentative', 'late'
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->unique(['raid_event_id', 'character_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raid_attendances');
    }
};
