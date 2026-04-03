<?php

namespace App\Models;

use App\Builders\ItemBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $icon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PriceSnapshot> $priceSnapshots
 * @method static ItemBuilder query()
 * @property-read int|null $price_snapshots_count
 * @method static ItemBuilder<static>|Item newModelQuery()
 * @method static ItemBuilder<static>|Item newQuery()
 * @method static ItemBuilder<static>|Item updateMetadata(int $id, string $name, ?string $icon)
 * @method static ItemBuilder<static>|Item whereCreatedAt($value)
 * @method static ItemBuilder<static>|Item whereIcon($value)
 * @method static ItemBuilder<static>|Item whereId($value)
 * @method static ItemBuilder<static>|Item whereName($value)
 * @method static ItemBuilder<static>|Item whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Item extends Model
{
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
