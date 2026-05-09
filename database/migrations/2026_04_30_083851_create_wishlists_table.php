<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->unsignedInteger('spec_id');
            $table->foreign('spec_id')->references('id')->on('specializations')->cascadeOnDelete();
            $table->string('raid_slug');
            $table->string('difficulty'); // mythic|heroic|normal|raid_finder
            $table->string('source'); // raidbots|qe_live
            $table->string('source_url');
            $table->string('source_report_id')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->unique(
                ['character_id', 'spec_id', 'raid_slug', 'difficulty'],
                'wishlists_char_spec_raid_diff_unique'
            );
            $table->index(['raid_slug', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
