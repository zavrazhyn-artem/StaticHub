<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('difficulty', 20)->default('mythic')->after('description');
            $table->string('status', 20)->default('planned')->after('difficulty');
            $table->boolean('is_optional')->default(false)->after('status');
            $table->json('encounter_order')->nullable()->after('is_optional');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['difficulty', 'status', 'is_optional', 'encounter_order']);
        });
    }
};
