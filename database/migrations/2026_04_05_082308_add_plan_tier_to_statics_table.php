<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->string('plan_tier', 20)
                  ->default('free')
                  ->after('wcl_last_synced_at')
                  ->comment('Subscription tier: free | premium | pro');
        });
    }

    public function down(): void
    {
        Schema::table('statics', function (Blueprint $table) {
            $table->dropColumn('plan_tier');
        });
    }
};
