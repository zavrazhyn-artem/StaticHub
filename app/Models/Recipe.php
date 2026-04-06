<?php

namespace App\Models;

use App\Builders\RecipeBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $blizzard_id
 * @property string $name
 * @property string $profession
 * @property int|null $output_item_id
 * @property int $yield_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * Virtual presentation properties (applied via ConsumableService):
 * @property string|null $display_icon
 * @property string|null $display_color
 * @property string|null $wow_zamimg_url
 * @property int|null $quantity
 * @property float|int|null $crafting_cost
 * @property int|null $default_quantity
 *
 * Relationships:
 * @property-read Item|null $outputItem
 * @property-read Collection<int, RecipeIngredient> $ingredients
 * @method static RecipeBuilder query()
 * @property-read int|null $ingredients_count
 * @method static RecipeBuilder<static>|Recipe newModelQuery()
 * @method static RecipeBuilder<static>|Recipe newQuery()
 * @method static RecipeBuilder<static>|Recipe whereBlizzardId($value)
 * @method static RecipeBuilder<static>|Recipe whereCreatedAt($value)
 * @method static RecipeBuilder<static>|Recipe whereId($value)
 * @method static RecipeBuilder<static>|Recipe whereName($value)
 * @method static RecipeBuilder<static>|Recipe whereOutputItemId($value)
 * @method static RecipeBuilder<static>|Recipe whereProfession($value)
 * @method static RecipeBuilder<static>|Recipe whereUpdatedAt($value)
 * @method static RecipeBuilder<static>|Recipe whereYieldQuantity($value)
 * @method static RecipeBuilder<static>|Recipe withNames(array $names)
 * @mixin \Eloquent
 */
class Recipe extends Model
{
    protected $fillable = ['name', 'profession', 'output_item_id', 'yield_quantity'];

    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'output_item_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function newEloquentBuilder($query): RecipeBuilder
    {
        return new RecipeBuilder($query);
    }
}
