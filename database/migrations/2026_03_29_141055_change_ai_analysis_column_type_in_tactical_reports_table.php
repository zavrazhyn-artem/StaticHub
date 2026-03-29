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
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->longText('ai_analysis')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->json('ai_analysis')->nullable()->change();
        });
    }
};
