<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_tactical_reports', function (Blueprint $table) {
            $table->json('ai_blocks_translations')->nullable()->after('ai_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('personal_tactical_reports', function (Blueprint $table) {
            $table->dropColumn('ai_blocks_translations');
        });
    }
};
