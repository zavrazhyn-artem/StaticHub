<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\WishlistBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $character_id
 * @property int $spec_id
 * @property string $raid_slug
 * @property string $difficulty
 * @property string $source
 * @property string $source_url
 * @property string|null $source_report_id
 * @property array|null $raw_payload
 * @property Carbon|null $generated_at
 * @property Carbon $imported_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Character $character
 * @property-read Specialization $specialization
 * @property-read Collection<int, WishlistItem> $items
 * @method static WishlistBuilder query()
 */
class Wishlist extends Model
{
    public const SOURCE_RAIDBOTS = 'raidbots';
    public const SOURCE_QE_LIVE = 'qe_live';

    protected $fillable = [
        'character_id',
        'spec_id',
        'raid_slug',
        'difficulty',
        'source',
        'source_url',
        'source_report_id',
        'raw_payload',
        'generated_at',
        'imported_at',
        'matched_config_id',
    ];

    public function matchedConfig(): BelongsTo
    {
        return $this->belongsTo(WishlistDroptimizerConfig::class, 'matched_config_id');
    }

    protected $casts = [
        'raw_payload' => 'array',
        'generated_at' => 'datetime',
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
        return $this->hasMany(WishlistItem::class);
    }

    public function newEloquentBuilder($query): WishlistBuilder
    {
        return new WishlistBuilder($query);
    }
}
