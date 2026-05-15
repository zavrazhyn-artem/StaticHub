<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\GearListBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $character_id
 * @property int $spec_id
 * @property string $type
 * @property string $name
 * @property string $source
 * @property string|null $source_url
 * @property Carbon|null $imported_at
 * @property string|null $talent_loadout_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Character $character
 * @property-read Specialization $specialization
 * @property-read Collection<int, GearListItem> $items
 * @method static GearListBuilder query()
 */
class GearList extends Model
{
    public const TYPE_CURRENT = 'current';
    public const TYPE_BIS = 'bis';
    public const TYPE_CUSTOM = 'custom';

    public const SOURCE_BNET = 'bnet';
    public const SOURCE_ICY_VEINS = 'icy_veins';
    public const SOURCE_SIMC = 'simc';
    public const SOURCE_MANUAL = 'manual';

    public const CUSTOM_LIMIT_PER_CONTEXT = 10;

    protected $fillable = [
        'character_id',
        'spec_id',
        'type',
        'name',
        'source',
        'source_url',
        'imported_at',
        'talent_loadout_code',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class, 'spec_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GearListItem::class, 'list_id');
    }

    public function newEloquentBuilder($query): GearListBuilder
    {
        return new GearListBuilder($query);
    }
}
