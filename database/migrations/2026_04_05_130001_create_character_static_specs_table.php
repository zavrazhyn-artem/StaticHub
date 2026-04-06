<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_static_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->unsignedBigInteger('static_id');
            $table->foreign('static_id')->references('id')->on('statics')->cascadeOnDelete();
            $table->unsignedInteger('spec_id');
            $table->foreign('spec_id')->references('id')->on('specializations')->cascadeOnDelete();
            $table->boolean('is_main')->default(false);
            $table->timestamps();

            $table->unique(['character_id', 'static_id', 'spec_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_static_specs');
    }
};
