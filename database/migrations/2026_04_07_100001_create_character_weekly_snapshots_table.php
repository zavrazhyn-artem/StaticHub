<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_weekly_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 10);   // e.g. "2026-W14"
            $table->string('region', 4);         // eu, us, kr, tw
            $table->json('weekly_data');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['character_id', 'period_key']);
            $table->index(['region', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_weekly_snapshots');
    }
};
