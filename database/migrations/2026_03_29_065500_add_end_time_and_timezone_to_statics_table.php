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
            $table->time('raid_end_time')->nullable()->after('raid_start_time');
            $table->string('timezone')->default('Europe/Paris')->after('raid_end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn(['raid_end_time', 'timezone']);
        });
    }
};
