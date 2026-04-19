<?php

namespace App\Models;

use App\Builders\EncounterSnapshotBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $static_id
 * @property int|null $tactical_report_id
 * @property string $boss_name
 * @property int|null $wcl_encounter_id
 * @property string|null $difficulty
 * @property Carbon $raid_date
 * @property int $duration_seconds
 * @property bool $killed
 * @property int|null $best_wipe_pct
 * @property int $attempts
 * @property int $total_deaths
 * @property array $player_metrics
 * @property array|null $encounter_summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static EncounterSnapshotBuilder query()
 */
class EncounterSnapshot extends Model
{
    public function newEloquentBuilder($query): EncounterSnapshotBuilder
    {
        return new EncounterSnapshotBuilder($query);
    }

    protected $fillable = [
        'static_id',
        'tactical_report_id',
        'boss_name',
        'wcl_encounter_id',
        'difficulty',
        'raid_date',
        'duration_seconds',
        'killed',
        'best_wipe_pct',
        'attempts',
        'total_deaths',
        'player_metrics',
        'encounter_summary',
    ];

    protected $casts = [
        'raid_date'         => 'datetime',
        'killed'            => 'boolean',
        'player_metrics'    => 'array',
        'encounter_summary' => 'array',
    ];

    public function staticGroup(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }

    public function tacticalReport(): BelongsTo
    {
        return $this->belongsTo(TacticalReport::class);
    }
}
