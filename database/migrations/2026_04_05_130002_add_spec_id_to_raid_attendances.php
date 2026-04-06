<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->unsignedInteger('spec_id')->nullable()->after('comment');
            $table->foreign('spec_id')->references('id')->on('specializations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('raid_attendances', function (Blueprint $table) {
            $table->dropForeign(['spec_id']);
            $table->dropColumn('spec_id');
        });
    }
};
