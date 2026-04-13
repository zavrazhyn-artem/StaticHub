<?php

namespace App\Models;

use App\Builders\EventBuilder;
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
 * @property string $difficulty
 * @property string $status
 * @property bool $is_optional
 * @property array|null $encounter_order
 * @property array|null $selected_encounters
 * @property bool $boss_roster_enabled
 * @property bool $split_enabled
 * @property int $split_count
 * @property string|null $discord_message_id
 * @property bool $raid_started
 * @property bool $raid_over
 * @property bool $ai_analysis_done
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StaticGroup $static
 * @property-read Collection<int, Character> $characters
 * @property-read Collection<int, RaidAttendance> $attendances
 * @property-read Collection<int, EventEncounterRoster> $encounterRosters
 * @property-read Collection<int, RaidPlan> $raidPlans
 * @property-read TacticalReport|null $tacticalReport
 * @method static EventBuilder query()
 * @property-read int|null $attendances_count
 * @property-read \App\Models\RaidAttendance|null $pivot
 * @property-read int|null $characters_count
 * @method static EventBuilder<static>|Event betweenDates(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate)
 * @method static EventBuilder<static>|Event forStatic(int $staticId)
 * @method static EventBuilder<static>|Event newModelQuery()
 * @method static EventBuilder<static>|Event newQuery()
 * @method static EventBuilder<static>|Event nextRaid(int $staticId)
 * @method static EventBuilder<static>|Event weeklySchedule(int $staticId)
 * @method static EventBuilder<static>|Event whereCreatedAt($value)
 * @method static EventBuilder<static>|Event whereDescription($value)
 * @method static EventBuilder<static>|Event whereDiscordMessageId($value)
 * @method static EventBuilder<static>|Event whereEndTime($value)
 * @method static EventBuilder<static>|Event whereId($value)
 * @method static EventBuilder<static>|Event whereStartTime($value)
 * @method static EventBuilder<static>|Event whereStaticId($value)
 * @method static EventBuilder<static>|Event whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Event extends Model
{
    protected $fillable = [
        'static_id',
        'start_time',
        'end_time',
        'timezone',
        'description',
        'difficulty',
        'status',
        'is_optional',
        'encounter_order',
        'selected_encounters',
        'boss_roster_enabled',
        'split_enabled',
        'split_count',
        'assigned_plans',
        'discord_message_id',
        'raid_started',
        'raid_over',
        'ai_analysis_done',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'difficulty' => 'string',
        'status' => 'string',
        'is_optional' => 'boolean',
        'encounter_order' => 'array',
        'selected_encounters' => 'array',
        'boss_roster_enabled' => 'boolean',
        'split_enabled' => 'boolean',
        'split_count' => 'integer',
        'assigned_plans' => 'array',
        'raid_started' => 'boolean',
        'raid_over' => 'boolean',
        'ai_analysis_done' => 'boolean',
    ];

    public function newEloquentBuilder($query): EventBuilder
    {
        return new EventBuilder($query);
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

    public function encounterRosters(): HasMany
    {
        return $this->hasMany(EventEncounterRoster::class);
    }

    public function raidPlans(): HasMany
    {
        return $this->hasMany(RaidPlan::class);
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
        $characterIds = $this->characters()
            ->where('user_id', $userId)
            ->pluck('characters.id');

        if ($characterIds->isNotEmpty()) {
            $this->characters()->detach($characterIds);
        }
    }
}
