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
        Schema::table('statics', function (Blueprint $table) {
            $table->string('wcl_guild_id')->nullable()->after('server');
            $table->string('wcl_region')->nullable()->after('wcl_guild_id');
            $table->string('wcl_realm')->nullable()->after('wcl_region');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('wcl_report_id')->nullable()->after('discord_message_id');
            $table->json('ai_analysis')->nullable()->after('wcl_report_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn(['wcl_guild_id', 'wcl_region', 'wcl_realm']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['wcl_report_id', 'ai_analysis']);
        });
    }
};
