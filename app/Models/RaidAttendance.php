<?php

namespace App\Models;

use App\Builders\RaidAttendanceBuilder;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int $character_id
 * @property string $status
 * @property string|null $comment
 * @property int|null $spec_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static RaidAttendanceBuilder query()
 * @method static RaidAttendanceBuilder<static>|RaidAttendance newModelQuery()
 * @method static RaidAttendanceBuilder<static>|RaidAttendance newQuery()
 * @method static RaidAttendanceBuilder<static>|RaidAttendance forEvent(int $eventId)
 * @method static RaidAttendanceBuilder<static>|RaidAttendance whereCharacterId($value)
 * @method static RaidAttendanceBuilder<static>|RaidAttendance whereComment($value)
 * @method static RaidAttendanceBuilder<static>|RaidAttendance whereCreatedAt($value)
 * @method static RaidAttendanceBuilder<static>|RaidAttendance whereId($value)
 * @method static RaidAttendanceBuilder<static>|RaidAttendance whereRaidEventId($value)
 * @method static RaidAttendanceBuilder<static>|RaidAttendance whereStatus($value)
 * @method static RaidAttendanceBuilder<static>|RaidAttendance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RaidAttendance extends Pivot
{
    protected $table = 'raid_attendances';

    protected $fillable = [
        'event_id',
        'character_id',
        'status',
        'comment',
        'spec_id',
        'split_group',
    ];

    public function newEloquentBuilder($query): RaidAttendanceBuilder
    {
        return new RaidAttendanceBuilder($query);
    }
}
