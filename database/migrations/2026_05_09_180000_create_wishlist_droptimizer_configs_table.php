<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-static "Allowed Droptimizer configurations" — mirrors wowaudit's
 * concept. Raid lead defines 1+ named config rows (e.g. "Single Target"
 * + "Cleave"). When a member imports a Raidbots Droptimizer wishlist,
 * the import service matches the report's metadata (fight_style,
 * num_bosses, fight_length, item upgrade tracks per difficulty) against
 * these rows and stamps the matching config_id on the wishlist. Items'
 * upgrade % then weight by config.weight in the static-wide overview.
 *
 * Comparison operators on num_bosses/fight_length use the same set as
 * wowaudit: is / at_least / at_most.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wishlist_droptimizer_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('static_id')->constrained('statics')->cascadeOnDelete();
            $table->string('display_name', 64);
            $table->string('fight_style', 32);                   // Patchwerk, DungeonSlice, HeavyMovement, etc.
            $table->string('num_bosses_op', 16)->default('is');  // is | at_least | at_most
            $table->unsignedSmallInteger('num_bosses')->default(1);
            $table->string('fight_length_op', 16)->default('at_least'); // same set
            $table->unsignedSmallInteger('fight_length_minutes')->default(5);
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->boolean('require_vault_socket')->default(false);
            $table->boolean('require_pi')->default(false);
            $table->boolean('voidforged')->default(false);
            $table->boolean('allow_expert')->default(false);
            $table->boolean('require_upgrade_all_same')->default(false);
            // Per-difficulty required upgrade ilvl. Stored as the resolved
            // ilvl integer (e.g. 289 for Myth 6/6 in season 17) so the
            // matcher doesn't have to know about track names.
            $table->unsignedSmallInteger('upgrade_ilvl_mythic')->nullable();
            $table->unsignedSmallInteger('upgrade_ilvl_heroic')->nullable();
            $table->unsignedSmallInteger('upgrade_ilvl_normal')->nullable();
            $table->unsignedSmallInteger('upgrade_ilvl_lfr')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['static_id', 'position']);
        });

        Schema::table('wishlists', function (Blueprint $table) {
            // Nullable because legacy/manual imports + reports that don't
            // match any config still need to land in the wishlist table —
            // the UI can flag them as "unmatched" instead of rejecting.
            $table->foreignId('matched_config_id')
                ->nullable()
                ->after('source_url')
                ->constrained('wishlist_droptimizer_configs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_config_id');
        });
        Schema::dropIfExists('wishlist_droptimizer_configs');
    }
};
