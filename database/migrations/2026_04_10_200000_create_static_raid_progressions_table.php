<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('static_raid_progressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('static_group_id')->constrained('statics')->cascadeOnDelete();
            $table->string('instance_name');
            $table->string('boss_name');
            $table->string('difficulty', 4); // LFR, N, H, M
            $table->timestamp('achieved_at');
            $table->timestamps();

            $table->unique(
                ['static_group_id', 'instance_name', 'boss_name', 'difficulty'],
                'static_raid_prog_unique'
            );

            $table->index('static_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_raid_progressions');
    }
};
