<?php

namespace App\Models;

use App\Builders\CharacterBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Character extends Model
{
    //

    protected $fillable = [
        'id',
        'user_id',
        'realm_id',
        'name',
        'playable_class',
        'playable_race',
        'level',
        'item_level',
        'equipped_item_level',
        'active_spec',
        'avatar_url',
    ];

    public function newEloquentBuilder($query): CharacterBuilder
    {
        return new CharacterBuilder($query);
    }

    /**
     * Get the user that owns the character.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the realm that the character belongs to.
     */
    public function realm(): BelongsTo
    {
        return $this->belongsTo(Realm::class);
    }

    /**
     * Get the statics for the character.
     */
    public function statics(): BelongsToMany
    {
        return $this->belongsToMany(StaticGroup::class, 'character_static', 'character_id', 'static_id')
            ->withPivot('role', 'combat_role')
            ->withTimestamps();
    }

    /**
     * Get the raid events the character is attending.
     */
    public function raidEvents(): BelongsToMany
    {
        return $this->belongsToMany(RaidEvent::class, 'raid_attendances')
            ->using(RaidAttendance::class)
            ->withPivot('status', 'comment')
            ->withTimestamps();
    }

    public function personalTacticalReports()
    {
        return $this->hasMany(PersonalTacticalReport::class);
    }
}
