<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $raid_event_id
 * @property int $character_id
 * @property string $status
 * @property string|null $comment
 * @property int|null $spec_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance whereCharacterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance whereRaidEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RaidAttendance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RaidAttendance extends Pivot
{
    protected $table = 'raid_attendances';

    protected $fillable = [
        'raid_event_id',
        'character_id',
        'status',
        'comment',
        'spec_id',
    ];
}
