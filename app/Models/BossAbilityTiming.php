<?php

namespace App\Models;

use App\Builders\BossAbilityTimingBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $season
 * @property string $encounter_slug
 * @property string $difficulty
 * @property int|null $static_id
 * @property int $spell_id
 * @property string $name
 * @property string|null $icon_filename
 * @property string $color
 * @property string|null $ability_type
 * @property array $default_casts
 * @property int $duration_sec
 * @property int $row_order
 * @property string|null $source_report_code
 * @property int|null $source_fight_id
 * @property \Illuminate\Support\Carbon|null $source_kill_time
 * @property \Illuminate\Support\Carbon|null $seeded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static BossAbilityTimingBuilder query()
 */
class BossAbilityTiming extends Model
{
    protected $fillable = [
        'season',
        'encounter_slug',
        'difficulty',
        'static_id',
        'spell_id',
        'name',
        'icon_filename',
        'color',
        'ability_type',
        'default_casts',
        'duration_sec',
        'row_order',
        'source_report_code',
        'source_fight_id',
        'source_kill_time',
        'seeded_at',
    ];

    protected $casts = [
        'default_casts' => 'array',
        'spell_id' => 'integer',
        'static_id' => 'integer',
        'duration_sec' => 'integer',
        'row_order' => 'integer',
        'source_fight_id' => 'integer',
        'source_kill_time' => 'datetime',
        'seeded_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): BossAbilityTimingBuilder
    {
        return new BossAbilityTimingBuilder($query);
    }
}
