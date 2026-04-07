<?php

namespace App\Models;

use App\Builders\CharacterWeeklySnapshotBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $character_id
 * @property string $period_key
 * @property string $region
 * @property array $weekly_data
 * @property Carbon|null $created_at
 * @property-read Character $character
 * @method static CharacterWeeklySnapshotBuilder query()
 */
class CharacterWeeklySnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'character_id',
        'period_key',
        'region',
        'weekly_data',
        'created_at',
    ];

    protected $casts = [
        'weekly_data'  => 'array',
        'created_at'   => 'datetime',
    ];

    public function newEloquentBuilder($query): CharacterWeeklySnapshotBuilder
    {
        return new CharacterWeeklySnapshotBuilder($query);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
