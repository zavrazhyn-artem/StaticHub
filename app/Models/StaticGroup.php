<?php

namespace App\Models;

use App\Builders\StaticGroupBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $invite_token
 * @property Carbon|null $invite_until
 * @property string $slug
 * @property string $region
 * @property int|null $wcl_guild_id
 * @property string|null $wcl_region
 * @property string|null $wcl_realm
 * @property int $owner_id
 * @property array|null $raid_days
 * @property string|null $raid_start_time
 * @property string|null $raid_end_time
 * @property string|null $timezone
 * @property string|null $discord_channel_id
 * @property string|null $discord_guild_id
 * @property array|null $automation_settings
 * @property array|null $consumable_settings
 * @property int $guild_tax_per_player
 * @property Carbon|null $bnet_last_synced_at
 * @property Carbon|null $rio_last_synced_at
 * @property Carbon|null $wcl_last_synced_at
 * @property string $plan_tier
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Character> $characters
 * @property-read Collection<int, Transaction> $transactions
 * @property-read Collection<int, TacticalReport> $tacticalReports
 * @method static StaticGroupBuilder query()
 * @property-read int|null $characters_count
 * @property-read int|null $members_count
 * @property-read int|null $tactical_reports_count
 * @property-read int|null $transactions_count
 * @method static StaticGroupBuilder<static>|StaticGroup findByInviteToken(string $token)
 * @method static StaticGroupBuilder<static>|StaticGroup firstForUser(int $userId)
 * @method static StaticGroupBuilder<static>|StaticGroup newModelQuery()
 * @method static StaticGroupBuilder<static>|StaticGroup newQuery()
 * @method static StaticGroupBuilder<static>|StaticGroup whereAutomationSettings($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereBnetLastSyncedAt($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereConsumableSettings($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereCreatedAt($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereDiscordChannelId($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereDiscordGuildId($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereGuildTaxPerPlayer($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereId($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereInviteToken($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereInviteUntil($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereName($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereOwnerId($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereRaidDays($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereRaidEndTime($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereRaidStartTime($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereRegion($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereRioLastSyncedAt($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereSlug($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereTimezone($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereUpdatedAt($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereUserIsMember(int $userId)
 * @method static StaticGroupBuilder<static>|StaticGroup whereWclGuildId($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereWclLastSyncedAt($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereWclRealm($value)
 * @method static StaticGroupBuilder<static>|StaticGroup whereWclRegion($value)
 * @mixin \Eloquent
 */
class StaticGroup extends Model
{
    protected $table = 'statics';

    protected $fillable = [
        'name',
        'invite_token',
        'invite_until',
        'slug',
        'server',
        'region',
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
        'discord_webhook_url',
        'automation_settings',
        'consumable_settings',
        'guild_tax_per_player',
        'weekly_tax_per_player',
        'bnet_last_synced_at',
        'rio_last_synced_at',
        'wcl_last_synced_at',
        'plan_tier',
    ];

    protected $casts = [
        'invite_until' => 'datetime',
        'raid_days' => 'array',
        'automation_settings' => 'array',
        'consumable_settings' => 'array',
        'weekly_tax_per_player' => 'integer',
        'bnet_last_synced_at' => 'datetime',
        'rio_last_synced_at' => 'datetime',
        'wcl_last_synced_at' => 'datetime',
        'plan_tier' => 'string',
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
            ->withPivot(['role', 'access_role', 'roster_status', 'balance', 'current_weekly_tax_covered'])
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
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'static_id');
    }

    public function tacticalReports(): HasMany
    {
        return $this->hasMany(TacticalReport::class, 'static_id');
    }

    /**
     * Check if a user is a member of this static group.
     */
    public function hasMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    /**
     * Add a user to this static group.
     */
    public function addMember(int $userId, string $role = 'member'): void
    {
        $this->members()->attach($userId, [
            'role' => $role,
            'access_role' => $role === 'owner' ? 'leader' : 'member',
            'roster_status' => 'core',
        ]);
    }

    /**
     * Assign a user as the owner of this static group.
     */
    public function assignOwner(int $userId): void
    {
        $this->members()->attach($userId, [
            'role' => 'owner',
            'access_role' => 'leader',
            'roster_status' => 'core',
        ]);
    }

    /**
     * Refresh the invite token if it's expired or missing.
     */
    public function refreshInviteTokenIfNeeded(): string
    {
        if ($this->invite_token && $this->invite_until && $this->invite_until->isFuture()) {
            return $this->invite_token;
        }

        $token = \Illuminate\Support\Str::random(12);

        $this->update([
            'invite_token' => $token,
            'invite_until' => now()->addDay(),
        ]);

        return $token;
    }

    /**
     * Get the quantity for a specific consumable, optionally from static-specific settings.
     */
    public function getConsumableQuantity(string $recipeName, int $defaultQuantity): int
    {
        return (int) ($this->consumable_settings['quantities'][$recipeName] ?? $defaultQuantity);
    }

    /**
     * Get raid days as an array.
     */
    public function getRaidDaysArray(): array
    {
        return is_array($this->raid_days) ? $this->raid_days : [];
    }
}
