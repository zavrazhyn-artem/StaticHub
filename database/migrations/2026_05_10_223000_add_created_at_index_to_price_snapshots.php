<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an index on price_snapshots.created_at so the daily prune command
 * (`auctions:prune`) can DELETE old rows via index range scan instead of a
 * full table scan. Without this, deleting from 12M rows takes 10+ minutes
 * and locks the table; with the index it takes seconds.
 *
 * The composite (item_id, created_at) is also useful for the existing
 * `latestPricesForItems` query — currently does a `MAX(created_at) GROUP BY
 * item_id` which is much faster as an index range lookup per item.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->index('created_at', 'price_snapshots_created_at_idx');
            $table->index(['item_id', 'created_at'], 'price_snapshots_item_id_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->dropIndex('price_snapshots_created_at_idx');
            $table->dropIndex('price_snapshots_item_id_created_at_idx');
        });
    }
};
