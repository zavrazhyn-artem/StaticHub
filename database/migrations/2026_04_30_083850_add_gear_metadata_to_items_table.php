<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('slot')->nullable()->after('icon');
            $table->string('raid_slug')->nullable()->after('slot');
            $table->string('encounter_slug')->nullable()->after('raid_slug');

            $table->index(['raid_slug', 'encounter_slug']);
            $table->index('slot');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['raid_slug', 'encounter_slug']);
            $table->dropIndex(['slot']);
            $table->dropColumn(['slot', 'raid_slug', 'encounter_slug']);
        });
    }
};
