<?php

namespace App\Models;

use App\Builders\StaticGroupBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class StaticGroup extends Model
{
    use HasFactory;

    protected $table = 'statics';

    protected $fillable = [
        'name',
        'invite_token',
        'invite_until',
        'slug',
        'region',
        'server',
        'wcl_guild_id',
        'wcl_region',
        'wcl_realm',
        'owner_id',
        'raid_days',
        'raid_start_time',
        'raid_end_time',
        'timezone',
        'discord_channel_id',
        'discord_guild_id',
        'automation_settings',
        'consumable_settings',
        'guild_tax_per_player',
    ];

    protected $casts = [
        'invite_until' => 'datetime',
        'raid_days' => 'array',
        'automation_settings' => 'array',
        'consumable_settings' => 'array',
    ];

    public function newEloquentBuilder($query): StaticGroupBuilder
    {
        return new StaticGroupBuilder($query);
    }

    /**
     * Get the owner of the static.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the members of the static.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'static_user', 'static_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the characters in the static.
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_static', 'static_id', 'character_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include statics the user belongs to.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('member', function (Builder $builder) {
            if (auth()->check()) {
                $builder->whereHas('members', function ($query) {
                    $query->where('user_id', auth()->id());
                });
            }
        });
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'static_id');
    }

    public function tacticalReports()
    {
        return $this->hasMany(TacticalReport::class, 'static_id');
    }
}
