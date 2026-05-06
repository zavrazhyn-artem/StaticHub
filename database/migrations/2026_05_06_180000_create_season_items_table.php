<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog of equippable gear available in the current season's raid + M+ +
 * catalyst rotation. Kept separate from the universal `items` table (which
 * also serves auction prices, recipe ingredients, and gear-list FKs across
 * older seasons) so season metadata can be wiped and re-seeded each season
 * without touching unrelated rows.
 *
 * The PK matches the Blizzard item id so other tables that already key on
 * item id (gear_list_items, wishlist_items) can still reference these rows
 * by id without a join — but no FK is added to keep the season catalog
 * fully decoupled from the rest of the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_items', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('icon')->nullable();

            // Source-of-truth slot label from the loot dump
            // ("Hands", "One-Hand", "Two-Hand", "Off Hand", "Shield",
            //  "Trinket", "Finger", "Ranged", ...). UI maps to the legacy
            // gear-list slot enum (head/finger_1/main_hand/...) at render time.
            $table->string('inventory_type', 32)->nullable();
            $table->string('armor_type', 16)->nullable();
            $table->unsignedSmallInteger('weapon_type')->nullable();

            $table->json('role')->nullable();   // ["DPS","Tank","Healer"]
            $table->json('stats')->nullable();  // {intellect:100,crit:54,...}

            // Source: raid|dungeon|catalyst — slug matches the existing
            // wishlist/gear convention (instance-{journalId} for raids/dungeons,
            // catalyst-{seasonSlug} for catalyst).
            $table->string('source_type', 16);
            $table->string('source_slug', 64);
            $table->string('encounter_slug', 64)->nullable();
            $table->unsignedInteger('encounter_id')->nullable();
            $table->string('boss_name')->nullable();

            $table->string('season_slug', 32)->index();
            $table->boolean('is_tier')->default(false);

            $table->timestamps();

            $table->index(['season_slug', 'source_type']);
            $table->index(['season_slug', 'inventory_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_items');
    }
};
