<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', static function (Blueprint $table): void {
            $table->json('compiled_data')->nullable()->after('raw_wcl_data');
        });
    }

    public function down(): void
    {
        Schema::table('characters', static function (Blueprint $table): void {
            $table->dropColumn('compiled_data');
        });
    }
};
