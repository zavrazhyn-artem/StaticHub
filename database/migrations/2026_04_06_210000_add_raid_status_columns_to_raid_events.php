<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('raid_started')->default(false)->after('discord_message_id');
            $table->boolean('raid_over')->default(false)->after('raid_started');
            $table->boolean('ai_analysis_done')->default(false)->after('raid_over');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['raid_started', 'raid_over', 'ai_analysis_done']);
        });
    }
};
