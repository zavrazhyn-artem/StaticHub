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
            $table->bigInteger('guild_tax_per_player')->default(0)->after('discord_guild_id');
        });
    }

    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn('guild_tax_per_player');
        });
    }
};
