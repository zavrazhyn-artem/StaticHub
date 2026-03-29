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
            $table->json('raid_days')->nullable()->after('server');
            $table->time('raid_start_time')->nullable()->after('raid_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn(['raid_days', 'raid_start_time']);
        });
    }
};
