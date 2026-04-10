<?php

namespace App\Models;

use App\Builders\RaidPlanBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $event_id
 * @property int $static_id
 * @property string $encounter_slug
 * @property string $difficulty
 * @property string|null $title
 * @property array $steps
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read StaticGroup $static
 * @method static RaidPlanBuilder query()
 */
class RaidPlan extends Model
{
    protected $fillable = [
        'event_id',
        'static_id',
        'encounter_slug',
        'difficulty',
        'title',
        'steps',
        'share_token',
    ];

    protected $casts = [
        'steps' => 'array',
    ];

    public function newEloquentBuilder($query): RaidPlanBuilder
    {
        return new RaidPlanBuilder($query);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function static(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }
}
