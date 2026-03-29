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
        Schema::table('character_static', function (Blueprint $table) {
            $table->enum('combat_role', ['tank', 'heal', 'mdps', 'rdps'])->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('character_static', function (Blueprint $table) {
            $table->dropColumn('combat_role');
        });
    }
};
