<?php

namespace App\Models;

use App\Builders\RaidEventBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RaidEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'static_id',
        'title',
        'start_time',
        'end_time',
        'description',
        'discord_message_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function newEloquentBuilder($query): RaidEventBuilder
    {
        return new RaidEventBuilder($query);
    }

    public function static(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }

    /**
     * Get the characters attending the event.
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'raid_attendances')
            ->using(RaidAttendance::class)
            ->withPivot('status', 'comment')
            ->withTimestamps();
    }

    /**
     * Get the attendances for the event.
     */
    public function attendances()
    {
        return $this->hasMany(RaidAttendance::class);
    }
}
