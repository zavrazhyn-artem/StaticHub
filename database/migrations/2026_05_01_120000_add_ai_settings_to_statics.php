<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->enum('ai_tone', ['friendly', 'neutral', 'strict'])
                ->default('neutral')
                ->after('plan_tier');

            $table->unsignedTinyInteger('ai_death_cutoff')
                ->default(5)
                ->after('ai_tone');
        });
    }

    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn(['ai_tone', 'ai_death_cutoff']);
        });
    }
};
