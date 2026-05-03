<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('format_version')
                ->default(1)
                ->after('prompt_version');
        });
    }

    public function down(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->dropColumn('format_version');
        });
    }
};
