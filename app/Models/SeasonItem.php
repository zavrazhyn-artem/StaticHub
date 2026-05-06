<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\SeasonItemBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Catalog row for a single equippable item available in the current season's
 * raid + M+ + catalyst rotation. Decoupled from the universal `items` table so
 * season metadata can be wiped/re-seeded without touching shared rows.
 *
 * @property int    $id
 * @property string $name
 * @property string|null $icon
 * @property string|null $inventory_type
 * @property string|null $armor_type
 * @property int|null    $weapon_type
 * @property array|null  $role
 * @property array|null  $stats
 * @property string $source_type
 * @property string $source_slug
 * @property string|null $encounter_slug
 * @property int|null    $encounter_id
 * @property string|null $boss_name
 * @property string $season_slug
 * @property bool   $is_tier
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static SeasonItemBuilder query()
 */
class SeasonItem extends Model
{
    /**
     * Slug of the season this catalog covers. Kept here (and in
     * MidnightS1LootSeeder) instead of config because the catalog is the
     * single source of truth for the season; bumping this slug is a code
     * change that travels with a new seeder + dump file.
     */
    public const CURRENT_SEASON_SLUG = 'mid-s1';

    /**
     * source_slug → display name. Lets the UI show "Maisara Caverns" instead
     * of "instance-1315". Kept on the model rather than in config so the
     * catalog code path is self-contained.
     */
    public const SOURCE_DISPLAY_NAMES = [
        'instance-1307'    => 'The Voidspire',
        'instance-1308'    => 'March on Quel\'Danas',
        'instance-1314'    => 'The Dreamrift',
        'instance-1201'    => 'Algeth\'ar Academy',
        'instance-1300'    => 'Magisters\' Terrace',
        'instance-1315'    => 'Maisara Caverns',
        'instance-1316'    => 'Nexus-Point Xenas',
        'instance-278'     => 'Pit of Saron',
        'instance-945'     => 'Seat of the Triumvirate',
        'instance-476'     => 'Skyreach',
        'instance-1299'    => 'Windrunner Spire',
        'catalyst-mid-s1'  => 'Catalyst',
    ];

    public static function displaySource(string $sourceSlug): string
    {
        return self::SOURCE_DISPLAY_NAMES[$sourceSlug] ?? $sourceSlug;
    }

    protected $table = 'season_items';

    protected $fillable = [
        'id', 'name', 'icon',
        'inventory_type', 'armor_type', 'weapon_type',
        'role', 'stats', 'real_stats', 'base_item_level',
        'source_type', 'source_slug', 'encounter_slug', 'encounter_id', 'boss_name',
        'season_slug', 'is_tier',
    ];

    protected $casts = [
        'role'       => 'array',
        'stats'      => 'array',
        'real_stats' => 'array',
        'is_tier'    => 'bool',
    ];

    public function newEloquentBuilder($query): SeasonItemBuilder
    {
        return new SeasonItemBuilder($query);
    }
}
