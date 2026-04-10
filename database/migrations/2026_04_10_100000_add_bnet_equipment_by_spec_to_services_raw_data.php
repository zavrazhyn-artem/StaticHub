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

        // Seed from existing bnet_equipment + character.active_spec
        DB::table('services_raw_data')
            ->join('characters', 'characters.id', '=', 'services_raw_data.character_id')
            ->whereNotNull('services_raw_data.bnet_equipment')
            ->whereNotNull('characters.active_spec')
            ->where('characters.active_spec', '!=', '')
            ->orderBy('services_raw_data.id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('services_raw_data')
                        ->where('id', $row->id)
                        ->update([
                            'bnet_equipment_by_spec' => json_encode([
                                $row->active_spec => json_decode($row->bnet_equipment, true),
                            ]),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('services_raw_data', function (Blueprint $table) {
            $table->dropColumn('bnet_equipment_by_spec');
        });
    }
};
