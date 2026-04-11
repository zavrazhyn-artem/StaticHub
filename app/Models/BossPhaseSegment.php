<?php

namespace App\Models;

use App\Builders\BossPhaseSegmentBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $season
 * @property string $encounter_slug
 * @property string $difficulty
 * @property int|null $static_id
 * @property string $segment_id
 * @property int $phase_id
 * @property string $phase_name
 * @property bool $is_intermission
 * @property int $seed_start
 * @property int $seed_duration
 * @property int $segment_order
 * @property string|null $source_report_code
 * @property int|null $source_fight_id
 * @property \Illuminate\Support\Carbon|null $seeded_at
 * @method static BossPhaseSegmentBuilder query()
 */
class BossPhaseSegment extends Model
{
    protected $fillable = [
        'season',
        'encounter_slug',
        'difficulty',
        'static_id',
        'segment_id',
        'phase_id',
        'phase_name',
        'is_intermission',
        'seed_start',
        'seed_duration',
        'segment_order',
        'source_report_code',
        'source_fight_id',
        'seeded_at',
    ];

    protected $casts = [
        'phase_id' => 'integer',
        'is_intermission' => 'boolean',
        'seed_start' => 'integer',
        'seed_duration' => 'integer',
        'segment_order' => 'integer',
        'static_id' => 'integer',
        'source_fight_id' => 'integer',
        'seeded_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): BossPhaseSegmentBuilder
    {
        return new BossPhaseSegmentBuilder($query);
    }
}
