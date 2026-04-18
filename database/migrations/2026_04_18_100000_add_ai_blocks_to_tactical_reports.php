<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->json('ai_blocks')->nullable()->after('ai_analysis');
        });

        Schema::table('personal_tactical_reports', function (Blueprint $table) {
            $table->json('ai_blocks')->nullable()->after('content');
            $table->longText('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->dropColumn('ai_blocks');
        });

        Schema::table('personal_tactical_reports', function (Blueprint $table) {
            $table->dropColumn('ai_blocks');
            $table->longText('content')->nullable(false)->change();
        });
    }
};
