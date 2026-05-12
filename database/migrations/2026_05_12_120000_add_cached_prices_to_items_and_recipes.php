<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('last_price')->nullable()->after('encounter_slug');
            $table->timestamp('last_price_at')->nullable()->after('last_price');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->unsignedBigInteger('crafting_cost')->nullable()->after('yield_quantity');
            $table->timestamp('crafting_cost_at')->nullable()->after('crafting_cost');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['last_price', 'last_price_at']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['crafting_cost', 'crafting_cost_at']);
        });
    }
};
