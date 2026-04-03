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
        Schema::table('static_user', function (Blueprint $table) {
            $table->string('access_role')->default('member')->after('role'); // leader, officer, member
            $table->string('roster_status')->default('core')->after('access_role'); // core, bench
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('static_user', function (Blueprint $table) {
            $table->dropColumn(['access_role', 'roster_status']);
        });
    }
};
