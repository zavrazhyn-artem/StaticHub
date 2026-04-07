<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new columns
        Schema::table('characters', function (Blueprint $table) {
            $table->json('character_data')->nullable()->after('mythic_rating');
            $table->json('character_weekly_data')->nullable()->after('character_data');
        });

        // 2. Migrate existing compiled_data → character_data (weekly_data stays null until next compile)
        DB::table('characters')
            ->whereNotNull('compiled_data')
            ->update([
                'character_data' => DB::raw('compiled_data'),
            ]);

        // 3. Drop old columns
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'compiled_data',
                'raw_bnet_data',
                'raw_raiderio_data',
                'raw_wcl_data',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->json('compiled_data')->nullable();
            $table->json('raw_bnet_data')->nullable();
            $table->json('raw_raiderio_data')->nullable();
            $table->json('raw_wcl_data')->nullable();
        });

        DB::table('characters')
            ->whereNotNull('character_data')
            ->update([
                'compiled_data' => DB::raw('character_data'),
            ]);

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['character_data', 'character_weekly_data']);
        });
    }
};
