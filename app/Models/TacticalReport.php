<?php

namespace App\Models;

use App\Builders\TacticalReportBuilder;
use Illuminate\Database\Eloquent\Model;

class TacticalReport extends Model
{
    /**
     * @param $query
     * @return TacticalReportBuilder
     */
    public function newEloquentBuilder($query): TacticalReportBuilder
    {
        return new TacticalReportBuilder($query);
    }

    protected $fillable = [
        'static_id',
        'raid_event_id',
        'wcl_report_id',
        'title',
        'ai_analysis',
    ];

    protected $casts = [
        //
    ];

    public function staticGroup()
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }

    public function raidEvent()
    {
        return $this->belongsTo(RaidEvent::class);
    }

    public function personalReports()
    {
        return $this->hasMany(PersonalTacticalReport::class);
    }
}
