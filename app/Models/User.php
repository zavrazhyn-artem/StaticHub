<?php

namespace App\Models;

use App\Builders\UserBuilder;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;


#[Fillable(['name', 'email', 'locale', 'password', 'battlenet_id', 'battletag', 'hide_battletag', 'avatar', 'discord_id', 'discord_username'])]
#[Hidden(['password', 'remember_token'])]
/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $battlenet_id
 * @property string|null $battletag
 * @property bool $hide_battletag
 * @property string|null $avatar
 * @property string|null $discord_id
 * @property string|null $discord_username
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, StaticGroup> $statics
 * @property-read Collection<int, StaticGroup> $ownedStatics
 * @property-read Collection<int, Character> $characters
 * @property-read Collection<int, Transaction> $transactions
 * @method static UserBuilder query()
 * @property-read int|null $characters_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read int|null $owned_statics_count
 * @property-read int|null $statics_count
 * @property-read int|null $transactions_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static UserBuilder<static>|User firstStaticForUser(int $userId)
 * @method static UserBuilder<static>|User hasAnyStatic(int $userId)
 * @method static UserBuilder<static>|User hasCharacterInAnyStatic(int $userId)
 * @method static UserBuilder<static>|User hasMainCharacter(int $userId)
 * @method static UserBuilder<static>|User inStatic(int $staticId)
 * @method static UserBuilder<static>|User newModelQuery()
 * @method static UserBuilder<static>|User newQuery()
 * @method static UserBuilder<static>|User whereAvatar($value)
 * @method static UserBuilder<static>|User whereBattlenetId($value)
 * @method static UserBuilder<static>|User whereBattletag($value)
 * @method static UserBuilder<static>|User whereCreatedAt($value)
 * @method static UserBuilder<static>|User whereDiscordId($value)
 * @method static UserBuilder<static>|User whereDiscordUsername($value)
 * @method static UserBuilder<static>|User whereEmail($value)
 * @method static UserBuilder<static>|User whereEmailVerifiedAt($value)
 * @method static UserBuilder<static>|User whereId($value)
 * @method static UserBuilder<static>|User whereName($value)
 * @method static UserBuilder<static>|User wherePassword($value)
 * @method static UserBuilder<static>|User whereRememberToken($value)
 * @method static UserBuilder<static>|User whereUpdatedAt($value)
 * @method static UserBuilder<static>|User withCharactersAndStatics()
 * @method static UserBuilder<static>|User withStatics()
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    /**
     * @param $query
     * @return UserBuilder
     */
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
            ->withPivot(['role', 'access_role', 'roster_status'])
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
     * Get the character that is a Guild Master.
     */
    public function getGuildMasterCharacter(): ?Character
    {
        return $this->characters->first(fn(Character $character) => property_exists($character, 'guild_rank') && $character->guild_rank === 0);
    }

    /**
     * Get alt characters for a specific static.
     *
     * @param int $staticId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAltCharactersForStatic(int $staticId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->characters()->whereHas('statics', function ($query) use ($staticId) {
            $query->where('statics.id', $staticId)
                ->where('character_static.role', 'alt');
        })->get();
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
            'hide_battletag' => 'boolean',
        ];
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get IDs of characters owned by the user.
     *
     * @return array<int>
     */
    public function getCharacterIds(): array
    {
        return $this->characters()->pluck('id')->toArray();
    }

    /**
     * Get the user's main character for a specific static.
     * Falls back to the first character if no specific main is found in that static.
     */
    public function getMainCharacterForStatic(?int $staticId): ?Character
    {
        if ($staticId) {
            $main = Character::query()->findMainInStatic($this->id, $staticId);
            if ($main) {
                return $main;
            }
        }

        // If no static or no main in static, try to find any main in any static
        return Character::query()
            ->where('user_id', $this->id)
            ->whereHas('statics', function ($query) {
                $query->where('character_static.role', 'main');
            })
            ->first() ?? $this->characters()->first();
    }

    /**
     * Public-facing display name.
     * When the user opted to hide their BattleTag, return their main character name
     * (per-static when provided, otherwise any main they have). Falls back to battletag/name.
     */
    public function getDisplayName(?int $staticId = null): string
    {
        if ($this->hide_battletag) {
            $main = $this->getMainCharacterForStatic($staticId);
            if ($main && $main->name) {
                return $main->name;
            }
        }

        return $this->battletag ?? $this->name;
    }

    /**
     * Playable class of the user's main character (per-static when provided).
     * Used to colour display names in UI.
     */
    public function getDisplayClass(?int $staticId = null): ?string
    {
        return $this->getMainCharacterForStatic($staticId)?->playable_class;
    }

    /**
     * Get the effective avatar URL for the user.
     * If a main character is chosen (for a specific static or globally), use its avatar.
     * Otherwise, fallback to the user's social avatar or UI-avatars.
     */
    public function getEffectiveAvatarUrl(?int $staticId = null): string
    {
        $mainCharacter = $this->getMainCharacterForStatic($staticId);

        if ($mainCharacter && $mainCharacter->avatar_url) {
            return $mainCharacter->avatar_url;
        }

        return $this->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->name);
    }
}
