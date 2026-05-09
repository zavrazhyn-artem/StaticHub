<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\WishlistDroptimizerConfigBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int    $id
 * @property int    $static_id
 * @property bool   $is_default
 * @property string $display_name
 * @property string $fight_style
 * @property string $num_bosses_op
 * @property int    $num_bosses
 * @property string $fight_length_op
 * @property int    $fight_length_minutes
 * @property float  $weight
 * @property bool   $require_vault_socket
 * @property bool   $require_pi
 * @property bool   $voidforged
 * @property bool   $allow_expert
 * @property bool   $require_upgrade_all_same
 * @property string|null $upgrade_level_mythic
 * @property string|null $upgrade_level_heroic
 * @property string|null $upgrade_level_normal
 * @property string|null $upgrade_level_lfr
 * @property int    $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static WishlistDroptimizerConfigBuilder query()
 */
class WishlistDroptimizerConfig extends Model
{
    /** Five comparison operators — same set wowaudit ships. */
    public const OPS = ['less_than', 'at_most', 'is', 'at_least', 'more_than'];

    /**
     * Fight styles surfaced in the picker. Values mirror Raidbots'
     * Droptimizer fightStyle URL param (camelCase, no spaces) so the
     * matcher can compare strings 1:1 with payload.fight_style. Display
     * labels in the UI add the spaces back ("Dungeon Slice", etc).
     */
    public const FIGHT_STYLES = [
        'Patchwerk',
        'DungeonSlice',
        'TargetDummy',
        'ExecutePatchwerk',
        'HecticAddCleave',
        'LightMovement',
        'HeavyMovement',
        'CastingPatchwerk',
        'CleaveAdd',
    ];

    protected $fillable = [
        'static_id', 'is_default', 'display_name', 'fight_style',
        'num_bosses_op', 'num_bosses',
        'fight_length_op', 'fight_length_minutes',
        'weight',
        'require_vault_socket', 'require_pi', 'voidforged',
        'allow_expert', 'require_upgrade_all_same',
        'upgrade_level_mythic', 'upgrade_level_heroic',
        'upgrade_level_normal', 'upgrade_level_lfr',
        'position',
    ];

    protected $casts = [
        'weight'                   => 'float',
        'num_bosses'               => 'integer',
        'fight_length_minutes'     => 'integer',
        'position'                 => 'integer',
        'require_vault_socket'     => 'boolean',
        'require_pi'               => 'boolean',
        'voidforged'               => 'boolean',
        'allow_expert'             => 'boolean',
        'require_upgrade_all_same' => 'boolean',
        'is_default'               => 'boolean',
    ];

    /** Default values for the auto-created Default config row. */
    public const DEFAULT_ATTRS = [
        'is_default'               => true,
        'display_name'             => 'Default',
        'fight_style'              => 'Patchwerk',
        'num_bosses_op'            => 'is',
        'num_bosses'               => 1,
        'fight_length_op'          => 'at_least',
        'fight_length_minutes'     => 5,
        'weight'                   => 1.00,
        'require_vault_socket'     => false,
        'require_pi'               => false,
        'voidforged'               => false,
        'allow_expert'             => false,
        'require_upgrade_all_same' => true,
        'upgrade_level_mythic'     => 'Myth 6/6',
        'upgrade_level_heroic'     => 'Hero 6/6',
        'upgrade_level_normal'     => 'Champion 6/6',
        'upgrade_level_lfr'        => 'Veteran 6/6',
    ];

    /** @return array<string, list<string>> */
    public static function upgradeLevelOptions(): array
    {
        $map = config('wow_season.wishlist_upgrade_levels', []);
        return [
            'mythic' => array_keys($map['mythic'] ?? []),
            'heroic' => array_keys($map['heroic'] ?? []),
            'normal' => array_keys($map['normal'] ?? []),
            'lfr'    => array_keys($map['lfr']    ?? []),
        ];
    }

    public static function resolveUpgradeIlvl(string $difficulty, ?string $level): ?int
    {
        if ($level === null || $level === '') return null;
        $map = config("wow_season.wishlist_upgrade_levels.$difficulty", []);
        return $map[$level] ?? null;
    }

    public function static(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }

    public function newEloquentBuilder($query): WishlistDroptimizerConfigBuilder
    {
        return new WishlistDroptimizerConfigBuilder($query);
    }
}
