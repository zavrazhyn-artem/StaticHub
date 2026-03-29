<?php

namespace App\Models;

use App\Builders\RealmBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Realm extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'region',
    ];

    public function newEloquentBuilder($query): RealmBuilder
    {
        return new RealmBuilder($query);
    }

    /**
     * Get the characters associated with the realm.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }
}
