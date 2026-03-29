<?php

namespace App\Models;

use App\Builders\PriceSnapshotBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
