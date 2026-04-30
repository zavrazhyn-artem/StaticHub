<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services_raw_data', function (Blueprint $table) {
            $table->json('bnet_statistics')->nullable()->after('bnet_equipment_by_spec');
        });
    }

    public function down(): void
    {
        Schema::table('services_raw_data', function (Blueprint $table) {
            $table->dropColumn('bnet_statistics');
        });
    }
};
