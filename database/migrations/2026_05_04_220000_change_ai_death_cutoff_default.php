<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Schema::change() requires doctrine/dbal which isn't installed.
        // Raw ALTER works for MySQL.
        DB::statement('ALTER TABLE statics MODIFY ai_death_cutoff TINYINT UNSIGNED NOT NULL DEFAULT 3');

        // Migrate existing values: anything >5 clamps to 5, 0/null stays default 3.
        DB::table('statics')->where('ai_death_cutoff', '>', 5)->update(['ai_death_cutoff' => 5]);
        DB::table('statics')->where('ai_death_cutoff', '<', 1)->update(['ai_death_cutoff' => 3]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE statics MODIFY ai_death_cutoff TINYINT UNSIGNED NOT NULL DEFAULT 5');
    }
};
