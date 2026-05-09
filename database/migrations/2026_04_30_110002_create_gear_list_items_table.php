<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gear_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('gear_lists')->cascadeOnDelete();
            $table->string('slot', 16);
            $table->integer('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->unsignedSmallInteger('item_level')->nullable();
            $table->unsignedInteger('enchant_id')->nullable();
            $table->json('bonus_ids')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            // One item per slot per list (overwrite on re-assign).
            $table->unique(['list_id', 'slot']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gear_list_items');
    }
};
