<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_auto_generated')->default(false)->after('description')->index();
        });

        // Backfill: mark existing events that were created by the scheduler.
        DB::table('events')
            ->where('description', 'Auto-generated raid session.')
            ->update(['is_auto_generated' => true]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['is_auto_generated']);
            $table->dropColumn('is_auto_generated');
        });
    }
};
