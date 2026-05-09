<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allowable classes per season_item. class_name uses the canonical Blizzard
 * label ("Death Knight", "Demon Hunter", ...) so equality joins with
 * characters.playable_class work without a lookup table.
 *
 * Spec eligibility is derived at read-time from (class_name match AND
 * season_items.role overlaps spec.role) — no separate per-spec pivot is
 * needed since liquidarmory's source data only exposes class+role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_item_class', function (Blueprint $table) {
            $table->unsignedInteger('season_item_id');
            $table->string('class_name', 32);

            $table->primary(['season_item_id', 'class_name']);
            $table->index('class_name');

            $table->foreign('season_item_id')
                ->references('id')->on('season_items')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_item_class');
    }
};
