<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $list_id
 * @property string $slot
 * @property int $item_id
 * @property int|null $item_level
 * @property int|null $enchant_id
 * @property array|null $bonus_ids
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GearList $list
 * @property-read Item $item
 */
class GearListItem extends Model
{
    protected $fillable = [
        'list_id',
        'slot',
        'item_id',
        'item_level',
        'enchant_id',
        'bonus_ids',
        'has_empty_socket',
        'gem_ids',
        'position',
    ];

    protected $casts = [
        'bonus_ids' => 'array',
        'gem_ids' => 'array',
        'item_level' => 'integer',
        'enchant_id' => 'integer',
        'has_empty_socket' => 'boolean',
        'position' => 'integer',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(GearList::class, 'list_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
