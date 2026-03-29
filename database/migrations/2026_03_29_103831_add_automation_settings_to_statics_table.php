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
            $table->json('automation_settings')->nullable()->after('discord_channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn('automation_settings');
        });
    }
};
