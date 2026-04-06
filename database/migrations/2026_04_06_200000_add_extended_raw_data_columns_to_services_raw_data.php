<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services_raw_data', function (Blueprint $table) {
            $table->json('bnet_completed_quests')->nullable()->after('bnet_achievement_statistics');
            $table->json('bnet_pvp_summary')->nullable()->after('bnet_completed_quests');
            $table->json('bnet_reputations')->nullable()->after('bnet_pvp_summary');
            $table->json('bnet_titles')->nullable()->after('bnet_reputations');
            $table->json('bnet_mounts')->nullable()->after('bnet_titles');
            $table->json('bnet_pets')->nullable()->after('bnet_mounts');
        });
    }

    public function down(): void
    {
        Schema::table('services_raw_data', function (Blueprint $table) {
            $table->dropColumn([
                'bnet_completed_quests',
                'bnet_pvp_summary',
                'bnet_reputations',
                'bnet_titles',
                'bnet_mounts',
                'bnet_pets',
            ]);
        });
    }
};
