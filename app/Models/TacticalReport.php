<?php

namespace App\Models;

use App\Builders\TacticalReportBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $static_id
 * @property int|null $event_id
 * @property string $wcl_report_id
 * @property string|null $title
 * @property array|null $difficulties
 * @property string|null $ai_analysis
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StaticGroup $staticGroup
 * @property-read Event|null $event
 * @property-read Collection<int, PersonalTacticalReport> $personalReports
 * @method static TacticalReportBuilder query()
 * @property-read int|null $personal_reports_count
 * @method static TacticalReportBuilder<static>|TacticalReport findWithRoster(int $id)
 * @method static TacticalReportBuilder<static>|TacticalReport forStatic(int $staticId, ?string $difficulty = null)
 * @method static TacticalReportBuilder<static>|TacticalReport newModelQuery()
 * @method static TacticalReportBuilder<static>|TacticalReport newQuery()
 * @method static TacticalReportBuilder<static>|TacticalReport whereAiAnalysis($value)
 * @method static TacticalReportBuilder<static>|TacticalReport whereCreatedAt($value)
 * @method static TacticalReportBuilder<static>|TacticalReport whereId($value)
 * @method static TacticalReportBuilder<static>|TacticalReport whereRaidEventId($value)
 * @method static TacticalReportBuilder<static>|TacticalReport whereStaticId($value)
 * @method static TacticalReportBuilder<static>|TacticalReport whereTitle($value)
 * @method static TacticalReportBuilder<static>|TacticalReport whereUpdatedAt($value)
 * @method static TacticalReportBuilder<static>|TacticalReport whereWclReportId($value)
 * @mixin \Eloquent
 */
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
        'event_id',
        'wcl_report_id',
        'title',
        'difficulties',
        'ai_analysis',
    ];

    protected $casts = [
        'difficulties' => 'array',
    ];

    public function staticGroup(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function personalReports(): HasMany
    {
        return $this->hasMany(PersonalTacticalReport::class);
    }
    /**
     * Get roster names from the report's static group.
     *
     * @return array<int, string>
     */
    public function getRosterCharacterNames(): array
    {
        return $this->staticGroup->characters->pluck('name')->toArray();
    }
}
