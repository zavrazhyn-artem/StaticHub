<?php

namespace App\Models;

use App\Builders\CharacterBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $realm_id
 * @property string $name
 * @property string|null $playable_class
 * @property string|null $playable_race
 * @property int|null $level
 * @property int|null $item_level
 * @property int|null $equipped_item_level
 * @property string|null $active_spec
 * @property string|null $avatar_url
 * @property int|null $ilvl
 * @property float|null $mythic_rating
 * @property array|null $character_data
 * @property array|null $character_weekly_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Realm|null $realm
 * @property-read Collection<int, StaticGroup> $statics
 * @property-read Collection<int, Event> $events
 * @property-read Collection<int, PersonalTacticalReport> $personalTacticalReports
 * @method static CharacterBuilder query()
 * @property-read int|null $personal_tactical_reports_count
 * @property-read \App\Models\RaidAttendance|null $pivot
 * @property-read int|null $events_count
 * @property-read int|null $statics_count
 * @method static CharacterBuilder<static>|Character belongingTo(int $userId)
 * @method static CharacterBuilder<static>|Character belongingToUserInStatic(int $userId, int $staticId)
 * @method static CharacterBuilder<static>|Character defaultOrder()
 * @method static CharacterBuilder<static>|Character findForRsvp(int $characterId, int $userId, int $staticId)
 * @method static CharacterBuilder<static>|Character findMainInStatic(int $userId, int $staticId)
 * @method static CharacterBuilder<static>|Character findUserCharacterInReport(int $userId, int $staticId, int $reportId)
 * @method static CharacterBuilder<static>|Character inStatic(int $staticId)
 * @method static CharacterBuilder<static>|Character newModelQuery()
 * @method static CharacterBuilder<static>|Character newQuery()
 * @method static CharacterBuilder<static>|Character orderedByStaticRole()
 * @method static CharacterBuilder<static>|Character syncFromBlizzard(array $apiData, int $userId, int $realmId, ?string $avatarUrl)
 * @method static CharacterBuilder<static>|Character whereActiveSpec($value)
 * @method static CharacterBuilder<static>|Character whereAvatarUrl($value)
 * @method static CharacterBuilder<static>|Character whereCreatedAt($value)
 * @method static CharacterBuilder<static>|Character whereEquippedItemLevel($value)
 * @method static CharacterBuilder<static>|Character whereId($value)
 * @method static CharacterBuilder<static>|Character whereIlvl($value)
 * @method static CharacterBuilder<static>|Character whereItemLevel($value)
 * @method static CharacterBuilder<static>|Character whereLevel($value)
 * @method static CharacterBuilder<static>|Character whereMythicRating($value)
 * @method static CharacterBuilder<static>|Character whereName($value)
 * @method static CharacterBuilder<static>|Character wherePlayableClass($value)
 * @method static CharacterBuilder<static>|Character wherePlayableRace($value)
 * @method static CharacterBuilder<static>|Character whereRawBnetData($value)
 * @method static CharacterBuilder<static>|Character whereRawRaiderioData($value)
 * @method static CharacterBuilder<static>|Character whereRawWclData($value)
 * @method static CharacterBuilder<static>|Character whereRealmId($value)
 * @method static CharacterBuilder<static>|Character whereUpdatedAt($value)
 * @method static CharacterBuilder<static>|Character whereUserId($value)
 * @method static CharacterBuilder<static>|Character withRoleAlt(int $staticId)
 * @method static CharacterBuilder<static>|Character withRoleMain(int $staticId)
 * @method static CharacterBuilder<static>|Character withStaticRole(int $staticId)
 * @method static CharacterBuilder<static>|Character withStatics()
 * @mixin \Eloquent
 */
class Character extends Model
{
    /**
     * @param $query
     * @return CharacterBuilder
     */
    public function newEloquentBuilder($query): CharacterBuilder
    {
        return new CharacterBuilder($query);
    }

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
        'ilvl',
        'mythic_rating',
        'character_data',
        'character_weekly_data',
    ];

    protected $casts = [
        'character_data' => 'array',
        'character_weekly_data' => 'array',
        'mythic_rating' => 'float',
    ];

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
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get CharacterStaticSpec records for this character.
     */
    public function characterStaticSpecs(): HasMany
    {
        return $this->hasMany(CharacterStaticSpec::class);
    }

    /**
     * Get the raid events the character is attending.
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'raid_attendances')
            ->using(RaidAttendance::class)
            ->withPivot('status', 'comment')
            ->withTimestamps();
    }

    public function personalTacticalReports(): HasMany
    {
        return $this->hasMany(PersonalTacticalReport::class);
    }

    public function serviceRawData(): HasOne
    {
        return $this->hasOne(ServiceRawData::class);
    }

    public function weeklySnapshots(): HasMany
    {
        return $this->hasMany(CharacterWeeklySnapshot::class);
    }

    /**
     * Downgrade a character from main to alt in a specific static.
     *
     * @param int $userId
     * @param int $staticId
     * @return void
     */
    public static function downgradeMainToAlt(int $userId, int $staticId): void
    {
        self::query()->belongingTo($userId)
            ->withRoleMain($staticId)
            ->each(function (Character $mainCharacter) use ($staticId) {
                $mainCharacter->statics()->updateExistingPivot($staticId, ['role' => 'alt']);
            });
    }

    /**
     * Get the URL for the class icon.
     */
    public function getClassIconUrl(): string
    {
        return \App\Support\IconHelper::classUrl($this->playable_class);
    }

    /**
     * Get the absolute URL for the class icon (for Discord).
     */
    public function getClassIconUrlAbsolute(): string
    {
        return \App\Support\IconHelper::classUrlAbsolute($this->playable_class);
    }

    /**
     * Get the URL for the role icon in a specific static.
     */
    public function getRoleIconUrl(?int $staticId = null): ?string
    {
        $role = $staticId ? $this->getCombatRoleInStatic($staticId) : null;
        return \App\Support\IconHelper::roleUrl($role);
    }

    /**
     * Get the absolute URL for the role icon (for Discord).
     */
    public function getRoleIconUrlAbsolute(?int $staticId = null): ?string
    {
        $role = $staticId ? $this->getCombatRoleInStatic($staticId) : null;
        return \App\Support\IconHelper::roleUrlAbsolute($role);
    }

    /**
     * Get all available specializations for this character in a specific static.
     */
    public function specsInStatic(int $staticId): Collection
    {
        return Specialization::whereIn('id',
            DB::table('character_static_specs')
                ->where('character_id', $this->id)
                ->where('static_id', $staticId)
                ->pluck('spec_id')
        )->get();
    }

    /**
     * Get the main specialization for this character in a specific static.
     */
    public function getMainSpecInStatic(int $staticId): ?Specialization
    {
        // Prefer the eager-loaded relation when available — callers that pre-load
        // `characterStaticSpecs.specialization` (e.g. BossPlannerController roster)
        // otherwise trigger 2 queries per character on the list.
        if ($this->relationLoaded('characterStaticSpecs')) {
            $record = $this->characterStaticSpecs
                ->first(fn ($s) => (int) $s->static_id === $staticId && $s->is_main);

            return $record?->specialization;
        }

        $record = $this->characterStaticSpecs()
            ->where('static_id', $staticId)
            ->where('is_main', true)
            ->with('specialization')
            ->first();

        return $record?->specialization;
    }

    /**
     * Get the combat role of the character in a specific static.
     * Derived from the main specialization's role.
     */
    public function getCombatRoleInStatic(int $staticId): string
    {
        $spec = $this->getMainSpecInStatic($staticId);

        return $spec?->role ?? 'rdps';
    }
}
