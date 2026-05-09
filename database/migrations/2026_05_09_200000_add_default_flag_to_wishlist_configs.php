<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each static gets exactly one mandatory "Default" config — the row the
 * Raidbots deep-link buttons read from. The flag stays out of the form
 * the user edits; we just hide the delete button for it server-side and
 * lazy-create the row when a static first hits the wishlist UI.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wishlist_droptimizer_configs', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('static_id');
            $table->index(['static_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_droptimizer_configs', function (Blueprint $table) {
            $table->dropIndex(['static_id', 'is_default']);
            $table->dropColumn('is_default');
        });
    }
};
