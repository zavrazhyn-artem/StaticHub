<?php

namespace App\Models;

use App\Builders\PersonalTacticalReportBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tactical_report_id
 * @property int $character_id
 * @property string|null $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TacticalReport $tacticalReport
 * @property-read Character $character
 * @method static PersonalTacticalReportBuilder query()
 * @method static PersonalTacticalReportBuilder<static>|PersonalTacticalReport forCharacters(array $characterIds)
 * @method static PersonalTacticalReportBuilder<static>|PersonalTacticalReport newModelQuery()
 * @method static PersonalTacticalReportBuilder<static>|PersonalTacticalReport newQuery()
 * @method static PersonalTacticalReportBuilder<static>|PersonalTacticalReport whereCharacterId($value)
 * @method static PersonalTacticalReportBuilder<static>|PersonalTacticalReport whereContent($value)
 * @method static PersonalTacticalReportBuilder<static>|PersonalTacticalReport whereCreatedAt($value)
 * @method static PersonalTacticalReportBuilder<static>|PersonalTacticalReport whereId($value)
 * @method static PersonalTacticalReportBuilder<static>|PersonalTacticalReport whereTacticalReportId($value)
 * @method static PersonalTacticalReportBuilder<static>|PersonalTacticalReport whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
        'ai_blocks',
        'ai_blocks_translations',
    ];

    protected $casts = [
        'ai_blocks'              => 'array',
        'ai_blocks_translations' => 'array',
    ];

    public function tacticalReport(): BelongsTo
    {
        return $this->belongsTo(TacticalReport::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
