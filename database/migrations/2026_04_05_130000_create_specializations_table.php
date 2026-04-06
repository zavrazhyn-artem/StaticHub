<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specializations', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary(); // Bnet spec ID
            $table->string('name');                   // "Blood", "Protection"
            $table->string('class_name');             // "Death Knight", "Warrior"
            $table->enum('role', ['tank', 'heal', 'mdps', 'rdps']);
            $table->string('icon_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specializations');
    }
};
