<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            // Free-form short version string (e.g. "v17") rather than int — we
            // may want suffixes like "v17-experimental" for A/B branches
            // without another schema change.
            $table->string('prompt_version', 32)->nullable()->index()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->dropIndex(['prompt_version']);
            $table->dropColumn('prompt_version');
        });
    }
};
