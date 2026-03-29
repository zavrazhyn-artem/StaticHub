<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Builders\UserBuilder;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'password', 'battlenet_id', 'battletag', 'avatar', 'discord_id', 'discord_username'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function newEloquentBuilder($query): UserBuilder
    {
        return new UserBuilder($query);
    }

    /**
     * Get the statics the user belongs to.
     */
    public function statics(): BelongsToMany
    {
        return $this->belongsToMany(StaticGroup::class, 'static_user', 'user_id', 'static_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the statics the user owns.
     */
    public function ownedStatics(): HasMany
    {
        return $this->hasMany(StaticGroup::class, 'owner_id');
    }

    /**
     * Get the characters owned by the user.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
