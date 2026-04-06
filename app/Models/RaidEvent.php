<?php

namespace App\Models;

use App\Builders\RaidEventBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $static_id
 * @property Carbon $start_time
 * @property Carbon|null $end_time
 * @property string|null $description
 * @property string|null $discord_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StaticGroup $static
 * @property-read Collection<int, Character> $characters
 * @property-read Collection<int, RaidAttendance> $attendances
 * @property-read TacticalReport|null $tacticalReport
 * @method static RaidEventBuilder query()
 * @property-read int|null $attendances_count
 * @property-read \App\Models\RaidAttendance|null $pivot
 * @property-read int|null $characters_count
 * @method static RaidEventBuilder<static>|RaidEvent betweenDates(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate)
 * @method static RaidEventBuilder<static>|RaidEvent forStatic(int $staticId)
 * @method static RaidEventBuilder<static>|RaidEvent newModelQuery()
 * @method static RaidEventBuilder<static>|RaidEvent newQuery()
 * @method static RaidEventBuilder<static>|RaidEvent nextRaid(int $staticId)
 * @method static RaidEventBuilder<static>|RaidEvent weeklySchedule(int $staticId)
 * @method static RaidEventBuilder<static>|RaidEvent whereCreatedAt($value)
 * @method static RaidEventBuilder<static>|RaidEvent whereDescription($value)
 * @method static RaidEventBuilder<static>|RaidEvent whereDiscordMessageId($value)
 * @method static RaidEventBuilder<static>|RaidEvent whereEndTime($value)
 * @method static RaidEventBuilder<static>|RaidEvent whereId($value)
 * @method static RaidEventBuilder<static>|RaidEvent whereStartTime($value)
 * @method static RaidEventBuilder<static>|RaidEvent whereStaticId($value)
 * @method static RaidEventBuilder<static>|RaidEvent whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RaidEvent extends Model
{
    protected $fillable = [
        'static_id',
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
            ->withPivot('status', 'comment', 'spec_id')
            ->withTimestamps();
    }

    /**
     * Get the attendances for the event.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(RaidAttendance::class);
    }

    public function tacticalReport(): HasOne
    {
        return $this->hasOne(TacticalReport::class);
    }

    /**
     * Get the attendance record for a specific user.
     */
    public function getUserAttendance(int $userId): ?RaidAttendance
    {
        /** @var Character|null $character */
        $character = $this->characters()
            ->where('user_id', $userId)
            ->first();

        return $character ? $character->pivot : null;
    }

    /**
     * Clear the attendance records for a specific user for this event.
     */
    public function clearUserAttendance(int $userId): void
    {
        $this->characters()
            ->where('user_id', $userId)
            ->detach();
    }
}
