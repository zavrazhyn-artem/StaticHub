<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gear_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->unsignedInteger('spec_id');
            $table->foreign('spec_id')->references('id')->on('specializations')->cascadeOnDelete();
            $table->string('type', 16); // current|bis|custom
            $table->string('name', 80);
            $table->string('source', 16); // bnet|icy_veins|simc|manual
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            // Singleton enforcement: at most one current and one bis per (char, spec).
            // Custom lists are unbounded by DB; the soft 10-cap is enforced in service.
            $table->index(['character_id', 'spec_id', 'type']);
        });

        // Partial unique enforcement is dialect-specific. Use a trigger-free
        // approach: enforce in app layer via GearListService. The composite
        // index above keeps lookups fast.
    }

    public function down(): void
    {
        Schema::dropIfExists('gear_lists');
    }
};
