<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['raid_slug', 'encounter_slug']);
            $table->renameColumn('raid_slug', 'source_slug');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('source_type', 16)->nullable()->after('source_slug');
            $table->index(['source_type', 'source_slug']);
            $table->index(['source_slug', 'encounter_slug']);
        });

        // Backfill: every existing row with a populated source_slug is from a raid
        // (only Raidbots droptimizer imports have run before this migration).
        DB::table('items')
            ->whereNotNull('source_slug')
            ->update(['source_type' => 'raid']);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_slug']);
            $table->dropIndex(['source_slug', 'encounter_slug']);
            $table->dropColumn('source_type');
            $table->renameColumn('source_slug', 'raid_slug');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->index(['raid_slug', 'encounter_slug']);
        });
    }
};
