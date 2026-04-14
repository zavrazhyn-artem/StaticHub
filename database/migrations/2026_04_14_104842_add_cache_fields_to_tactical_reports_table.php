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
            $table->string('gemini_cache_id', 255)->nullable()->after('model');
            $table->timestamp('gemini_cache_expires_at')->nullable()->after('gemini_cache_id');
        });
    }

    public function down(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->dropColumn(['gemini_cache_id', 'gemini_cache_expires_at']);
        });
    }
};
