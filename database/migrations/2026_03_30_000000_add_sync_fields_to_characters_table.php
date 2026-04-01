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
        Schema::table('characters', function (Blueprint $table) {
            if (!Schema::hasColumn('characters', 'ilvl')) {
                $table->integer('ilvl')->nullable()->after('equipped_item_level');
            }
            if (!Schema::hasColumn('characters', 'mythic_rating')) {
                $table->decimal('mythic_rating', 8, 2)->nullable()->after('ilvl');
            }
            if (!Schema::hasColumn('characters', 'raw_bnet_data')) {
                $table->json('raw_bnet_data')->nullable()->after('avatar_url');
            }
            if (!Schema::hasColumn('characters', 'raw_raiderio_data')) {
                $table->json('raw_raiderio_data')->nullable()->after('raw_bnet_data');
            }
            if (!Schema::hasColumn('characters', 'raw_wcl_data')) {
                $table->json('raw_wcl_data')->nullable()->after('raw_raiderio_data');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'ilvl',
                'mythic_rating',
                'raw_bnet_data',
                'raw_raiderio_data',
                'raw_wcl_data',
            ]);
        });
    }
};
