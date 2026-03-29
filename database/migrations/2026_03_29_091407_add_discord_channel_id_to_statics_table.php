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
            $table->string('discord_channel_id')->nullable()->after('raid_days');
        });
    }

    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn('discord_channel_id');
        });
    }
};
