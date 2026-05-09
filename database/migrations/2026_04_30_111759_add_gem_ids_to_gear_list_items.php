<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gear_list_items', function (Blueprint $table) {
            $table->json('gem_ids')->nullable()->after('has_empty_socket');
        });
    }

    public function down(): void
    {
        Schema::table('gear_list_items', function (Blueprint $table) {
            $table->dropColumn('gem_ids');
        });
    }
};
