<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gear_list_items', function (Blueprint $table) {
            $table->boolean('has_empty_socket')->default(false)->after('bonus_ids');
        });
    }

    public function down(): void
    {
        Schema::table('gear_list_items', function (Blueprint $table) {
            $table->dropColumn('has_empty_socket');
        });
    }
};
