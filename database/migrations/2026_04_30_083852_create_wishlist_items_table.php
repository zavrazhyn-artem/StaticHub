<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained('wishlists')->cascadeOnDelete();
            $table->integer('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->unsignedInteger('value')->default(0); // raw DPS/HPS gain from sim
            $table->decimal('percent', 6, 3)->default(0); // upgrade percent (e.g. 12.345)
            $table->char('status', 1)->default('n'); // b=BIS, n=Not best, o=Outdated
            $table->text('comment')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['wishlist_id', 'value']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
