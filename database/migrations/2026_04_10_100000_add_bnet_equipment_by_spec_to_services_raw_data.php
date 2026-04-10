<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services_raw_data', function (Blueprint $table) {
            $table->json('bnet_equipment_by_spec')->nullable()->after('bnet_equipment');
        });

        // Seed from existing bnet_equipment + character.active_spec using pure SQL
        DB::statement("
            UPDATE services_raw_data srd
            JOIN characters c ON c.id = srd.character_id
            SET srd.bnet_equipment_by_spec = JSON_OBJECT(c.active_spec, srd.bnet_equipment)
            WHERE srd.bnet_equipment IS NOT NULL
              AND c.active_spec IS NOT NULL
              AND c.active_spec != ''
        ");
    }

    public function down(): void
    {
        Schema::table('services_raw_data', function (Blueprint $table) {
            $table->dropColumn('bnet_equipment_by_spec');
        });
    }
};
