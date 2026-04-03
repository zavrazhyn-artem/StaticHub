<?php

namespace App\Models;

use App\Builders\RealmBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $region
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Character> $characters
 * @method static RealmBuilder query()
 * @property string|null $timezone
 * @property int $is_online
 * @property-read int|null $characters_count
 * @method static RealmBuilder<static>|Realm findBySlug(string $slug)
 * @method static RealmBuilder<static>|Realm newModelQuery()
 * @method static RealmBuilder<static>|Realm newQuery()
 * @method static RealmBuilder<static>|Realm orderedByName()
 * @method static RealmBuilder<static>|Realm whereCreatedAt($value)
 * @method static RealmBuilder<static>|Realm whereId($value)
 * @method static RealmBuilder<static>|Realm whereIsOnline($value)
 * @method static RealmBuilder<static>|Realm whereName($value)
 * @method static RealmBuilder<static>|Realm whereRegion($value)
 * @method static RealmBuilder<static>|Realm whereSlug($value)
 * @method static RealmBuilder<static>|Realm whereTimezone($value)
 * @method static RealmBuilder<static>|Realm whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
