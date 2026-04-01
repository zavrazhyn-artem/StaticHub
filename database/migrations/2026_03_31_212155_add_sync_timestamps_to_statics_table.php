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
            $table->timestamp('bnet_last_synced_at')->nullable();
            $table->timestamp('rio_last_synced_at')->nullable();
            $table->timestamp('wcl_last_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn(['bnet_last_synced_at', 'rio_last_synced_at', 'wcl_last_synced_at']);
        });
    }
};
