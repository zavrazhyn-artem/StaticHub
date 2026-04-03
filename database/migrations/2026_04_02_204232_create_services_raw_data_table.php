<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services_raw_data', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('character_id')
                ->constrained('characters')
                ->cascadeOnDelete();

            // Blizzard API routes
            $table->json('bnet_profile')->nullable();
            $table->json('bnet_equipment')->nullable();
            $table->json('bnet_media')->nullable();
            $table->json('bnet_mplus')->nullable();
            $table->json('bnet_raid')->nullable();

            // Raider.io API routes
            $table->json('rio_profile')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services_raw_data');
    }
};
