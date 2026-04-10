<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\StaticRaidProgressionBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaticRaidProgression extends Model
{
    protected $table = 'static_raid_progressions';

    protected $fillable = [
        'static_group_id',
        'instance_name',
        'boss_name',
        'difficulty',
        'achieved_at',
    ];

    protected $casts = [
        'achieved_at' => 'datetime',
    ];

    public function staticGroup(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_group_id');
    }

    public function newEloquentBuilder($query): StaticRaidProgressionBuilder
    {
        return new StaticRaidProgressionBuilder($query);
    }
}
