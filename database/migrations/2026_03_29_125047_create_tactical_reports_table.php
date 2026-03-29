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
        Schema::create('tactical_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('static_id')->constrained('statics')->onDelete('cascade');
            $table->foreignId('raid_event_id')->nullable()->constrained('raid_events')->onDelete('set null');
            $table->string('wcl_report_id');
            $table->string('title')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tactical_reports');
    }
};
