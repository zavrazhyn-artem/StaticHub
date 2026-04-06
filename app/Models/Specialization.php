<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\SpecializationBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static SpecializationBuilder query()
 * @method static SpecializationBuilder<static>|Specialization newModelQuery()
 * @method static SpecializationBuilder<static>|Specialization newQuery()
 * @method static SpecializationBuilder<static>|Specialization forClass(string $className)
 */
class Specialization extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'name',
        'class_name',
        'role',
        'icon_url',
    ];

    /**
     * Filter specs by WoW class name (e.g. "Death Knight", "Warrior").
     */
    public function scopeForClass(Builder $query, string $className): Builder
    {
        return $query->where('class_name', $className);
    }

    public function newEloquentBuilder($query): SpecializationBuilder
    {
        return new SpecializationBuilder($query);
    }
}
