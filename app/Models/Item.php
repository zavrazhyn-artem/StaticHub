<?php

namespace App\Models;

use App\Builders\ItemBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'name', 'icon'];

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }

    public function newEloquentBuilder($query): ItemBuilder
    {
        return new ItemBuilder($query);
    }
}
