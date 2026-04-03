<?php

namespace App\Models;

use App\Builders\PriceSnapshotBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $item_id
 * @property int $price
 * @property Carbon $created_at
 * @property-read Item $item
 * @method static PriceSnapshotBuilder query()
 * @method static PriceSnapshotBuilder<static>|PriceSnapshot latestPriceForItem(int $itemId)
 * @method static PriceSnapshotBuilder<static>|PriceSnapshot newModelQuery()
 * @method static PriceSnapshotBuilder<static>|PriceSnapshot newQuery()
 * @method static PriceSnapshotBuilder<static>|PriceSnapshot whereCreatedAt($value)
 * @method static PriceSnapshotBuilder<static>|PriceSnapshot whereId($value)
 * @method static PriceSnapshotBuilder<static>|PriceSnapshot whereItemId($value)
 * @method static PriceSnapshotBuilder<static>|PriceSnapshot wherePrice($value)
 * @mixin \Eloquent
 */
class PriceSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['item_id', 'price', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Use custom Eloquent builder.
     */
    public function newEloquentBuilder($query): PriceSnapshotBuilder
    {
        return new PriceSnapshotBuilder($query);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
