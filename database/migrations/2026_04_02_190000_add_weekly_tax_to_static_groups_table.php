<?php

declare(strict_types=1);

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
        Schema::table('statics', function (Blueprint $table) {
            $table->integer('weekly_tax_per_player')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn('weekly_tax_per_player');
        });
    }
};
