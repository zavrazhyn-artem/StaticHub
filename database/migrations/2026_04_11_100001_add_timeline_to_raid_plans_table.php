<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raid_plans', function (Blueprint $table) {
            $table->json('timeline')->nullable()->after('steps');
        });
    }

    public function down(): void
    {
        Schema::table('raid_plans', function (Blueprint $table) {
            $table->dropColumn('timeline');
        });
    }
};
