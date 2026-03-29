<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalTacticalReport extends Model
{
    protected $fillable = [
        'tactical_report_id',
        'character_id',
        'content',
    ];

    public function tacticalReport()
    {
        return $this->belongsTo(TacticalReport::class);
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
