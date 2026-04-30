<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('item_level')->nullable()->after('item_id');
            $table->unsignedInteger('enchant_id')->nullable()->after('item_level');
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn(['item_level', 'enchant_id']);
        });
    }
};
