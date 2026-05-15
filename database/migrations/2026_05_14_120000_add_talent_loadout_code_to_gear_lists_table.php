<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gear_lists', function (Blueprint $table) {
            $table->string('talent_loadout_code', 512)->nullable()->after('imported_at');
        });
    }

    public function down(): void
    {
        Schema::table('gear_lists', function (Blueprint $table) {
            $table->dropColumn('talent_loadout_code');
        });
    }
};
