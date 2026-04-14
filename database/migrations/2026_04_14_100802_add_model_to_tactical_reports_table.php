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
            $table->string('model', 50)->nullable()->after('ai_analysis');
        });
    }

    public function down(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }
};
