<?php

namespace App\Models;

use App\Builders\PersonalTacticalReportBuilder;
use Illuminate\Database\Eloquent\Model;

class PersonalTacticalReport extends Model
{
    /**
     * @param $query
     * @return PersonalTacticalReportBuilder
     */
    public function newEloquentBuilder($query): PersonalTacticalReportBuilder
    {
        return new PersonalTacticalReportBuilder($query);
    }
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
