<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tactical_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('report_rating');         // 1..5
            $table->unsignedTinyInteger('chat_rating')->nullable(); // null when chat unused

            // Tag pools — separate columns so admin queries can group/count
            // positive vs negative signal without parsing tag prefixes.
            $table->json('liked_tags')->nullable();
            $table->json('disliked_tags')->nullable();

            $table->text('comment')->nullable();
            $table->timestamps();

            // One feedback per (user, report). Re-submission updates instead.
            $table->unique(['tactical_report_id', 'user_id']);

            // Admin queries filter by rating windows often.
            $table->index(['report_rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_feedback');
    }
};
