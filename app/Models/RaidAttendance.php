<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RaidAttendance extends Pivot
{
    protected $table = 'raid_attendances';

    protected $fillable = [
        'raid_event_id',
        'character_id',
        'status',
        'comment',
    ];
}
