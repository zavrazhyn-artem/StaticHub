<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->string('discord_webhook_url')->nullable()->after('discord_channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn('discord_webhook_url');
        });
    }
};
