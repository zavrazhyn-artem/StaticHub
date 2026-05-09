<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wishlist_id
 * @property int $item_id
 * @property int $value
 * @property float $percent
 * @property string $status
 * @property string|null $comment
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wishlist $wishlist
 * @property-read Item $item
 */
class WishlistItem extends Model
{
    public const STATUS_BIS = 'b';
    public const STATUS_NOT_BEST = 'n';
    public const STATUS_OUTDATED = 'o';

    protected $fillable = [
        'wishlist_id',
        'item_id',
        'item_level',
        'enchant_id',
        'value',
        'percent',
        'status',
        'comment',
        'position',
    ];

    protected $casts = [
        'value' => 'integer',
        'percent' => 'float',
        'position' => 'integer',
        'item_level' => 'integer',
        'enchant_id' => 'integer',
    ];

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
