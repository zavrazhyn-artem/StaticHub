<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Helpers\WeeklyResetHelper;
use App\Http\Resources\StaticRosterMemberResource;
use App\Jobs\Character\FetchBnetRawDataJob;
use App\Jobs\Character\FetchRioRawDataJob;
use App\Models\Character;
use App\Models\CharacterStaticSpec;
use App\Models\CharacterWeeklySnapshot;
use App\Models\Specialization;
use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class RosterService
{
    /**
     * Build the full roster index payload for a static group.
     * Always returns live (current week) data. Historical snapshots are loaded via API.
     */
    public function buildRosterIndexPayload(StaticGroup $static): array
    {
        $members = $static->members()
            ->with([
                'characters' => fn ($q) => $q->with([
                    'statics' => fn ($sq) => $sq->where('statics.id', $static->id),
                ]),
            ])
            ->get();

        $currentUserId    = (int) Auth::id();
        $currentUserAccess = $members
            ->first(fn (User $m) => $m->id === $currentUserId)
            ?->pivot
            ?->access_role ?? 'member';

        if ((int) $static->owner_id === $currentUserId) {
            $currentUserAccess = 'leader';
        }

        // Pre-load all specs for all characters in this static (one query, no N+1).
        $allCharacterIds = $members->flatMap(fn (User $u) => $u->characters->pluck('id'));
        $allSpecRecords = CharacterStaticSpec::whereIn('character_id', $allCharacterIds)
            ->where('static_id', $static->id)
            ->with('specialization')
            ->get()
            ->groupBy('character_id');

        // Pre-load all specializations for resolving off-spec icons from gear data.
        $specLookup = Specialization::all()->keyBy(fn ($s) => "{$s->name}|{$s->class_name}");

        // Attach main_spec + all_specs attributes to each character.
        $members->each(function (User $user) use ($allSpecRecords, $specLookup) {
            $user->characters->each(function ($char) use ($allSpecRecords, $specLookup) {
                $charSpecs = $allSpecRecords->get($char->id, collect());

                $mainRecord = $charSpecs->firstWhere('is_main', true);
                $mainSpec = $mainRecord?->specialization;
                $char->setAttribute('main_spec', $mainSpec ? [
                    'id'       => $mainSpec->id,
                    'name'     => $mainSpec->name,
                    'role'     => $mainSpec->role,
                    'icon_url' => $mainSpec->icon_url,
                ] : null);

                $allSpecs = $charSpecs
                    ->map(fn ($record) => $record->specialization ? [
                        'id'       => $record->specialization->id,
                        'name'     => $record->specialization->name,
                        'role'     => $record->specialization->role,
                        'icon_url' => $record->specialization->icon_url,
                    ] : null)
                    ->filter()
                    ->values()
                    ->toArray();

                // Add specs from gear data that aren't in character_static_specs
                $knownSpecNames = collect($allSpecs)->pluck('name')->flip();
                $gearSpecNames = array_keys($char->character_data['equipment_by_spec'] ?? []);
                $className = $char->playable_class;

                foreach ($gearSpecNames as $specName) {
                    if (isset($knownSpecNames[$specName])) {
                        continue;
                    }

                    $spec = $specLookup["{$specName}|{$className}"] ?? null;
                    if ($spec) {
                        $allSpecs[] = [
                            'id'       => $spec->id,
                            'name'     => $spec->name,
                            'role'     => $spec->role,
                            'icon_url' => $spec->icon_url,
                        ];
                    }
                }

                $char->setAttribute('all_specs', $allSpecs);
            });
        });

        $region           = strtolower($static->region ?? 'eu');
        $currentPeriodKey = WeeklyResetHelper::periodKey($region);
        $availableWeeks   = $this->buildAvailableWeeks($region);

        // Build static raid columns from config so the grid never disappears
        $raidInstances = config('wow_season.current_raid_instances', []);
        $raidColumns   = [];
        foreach ($raidInstances as $name => $bosses) {
            $raidColumns[] = ['name' => $name, 'bosses' => $bosses];
        }

        return [
            'roster' => $members
                ->map(fn (User $user) => (new StaticRosterMemberResource($user))
                    ->setStaticId($static->id)
                    ->resolve()
                )
                ->values(),
            'current_user_access' => $currentUserAccess,
            'current_week'        => $currentPeriodKey,
            'available_weeks'     => $availableWeeks,
            'raid_columns'        => $raidColumns,
        ];
    }

    /**
     * Load weekly snapshot data for a specific past week.
     * Returns [ characterId => weeklyData array ] for all characters in the static.
     */
    public function getWeeklySnapshotData(StaticGroup $static, string $periodKey): array
    {
        $characterIds = $static->characters()->pluck('characters.id');

        if ($characterIds->isEmpty()) {
            return [];
        }

        return CharacterWeeklySnapshot::query()
            ->whereIn('character_id', $characterIds)
            ->forPeriod($periodKey)
            ->pluck('weekly_data', 'character_id')
            ->map(fn ($data) => is_string($data) ? json_decode($data, true) : $data)
            ->toArray();
    }

    /**
     * Build list of all season weeks from season_start to current week.
     * Returns array of [ { key: "2026-W10", number: 1, current: bool }, ... ]
     * ordered ascending (Week 1 first).
     */
    private function buildAvailableWeeks(string $region): array
    {
        $currentPeriodKey = WeeklyResetHelper::periodKey($region);
        $seasonStart      = strtotime(config('wow_season.season_start', 'now'));

        if ($seasonStart === false) {
            return [['key' => $currentPeriodKey, 'number' => 1, 'current' => true]];
        }

        $weeks      = [];
        $weekNumber = 1;
        $cursor     = $seasonStart;

        while (true) {
            $periodKey = gmdate('o-\WW', $cursor);
            $isCurrent = $periodKey === $currentPeriodKey;

            $weeks[] = [
                'key'     => $periodKey,
                'number'  => $weekNumber,
                'current' => $isCurrent,
            ];

            if ($isCurrent) {
                break;
            }

            $cursor += 7 * 86400;
            $weekNumber++;

            // Safety: don't loop more than 52 weeks
            if ($weekNumber > 52) {
                break;
            }
        }

        return $weeks;
    }

    /**
     * Get all members (users) of a static with their characters, grouped by role.
     */
    public function getGroupedRoster(int $staticId): Collection
    {
        $users = $this->getStaticMembers($staticId);

        $users->each(function (User $user) use ($staticId) {
            $main = $user->characters->first(
                fn ($char) => $char->statics->first()?->pivot->role === 'main'
            );
            $alts = $user->characters->filter(
                fn ($char) => $char->statics->first()?->pivot->role !== 'main'
            )->values();

            $user->setRelation('mainCharacter', $main);
            $user->setRelation('altCharacters', $alts);
        });

        return $users->groupBy(function (User $user) {
            $main = $user->mainCharacter;
            return $main ? ($main->main_spec['role'] ?? 'rdps') : 'unknown';
        });
    }

    /**
     * Get all members (users) of a static with their characters.
     * Also attaches main_spec attribute on each character for frontend use.
     */
    public function getStaticMembers(int $staticId): Collection
    {
        $users = User::query()->inStatic($staticId)
            ->with(['characters' => function ($query) use ($staticId) {
                $query->whereHas('statics', function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                })
                ->with(['statics' => function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                }]);
            }])
            ->get();

        // Pre-load main specs for all characters in this static in one query
        $allCharacterIds = $users->flatMap(fn ($u) => $u->characters->pluck('id'));

        $mainSpecRecords = CharacterStaticSpec::whereIn('character_id', $allCharacterIds)
            ->where('static_id', $staticId)
            ->where('is_main', true)
            ->with('specialization')
            ->get()
            ->keyBy('character_id');

        // Set main_spec attribute on each character
        $users->each(function ($user) use ($mainSpecRecords) {
            $user->characters->each(function ($char) use ($mainSpecRecords) {
                $specRecord = $mainSpecRecords->get($char->id);
                $spec = $specRecord?->specialization;

                $char->setAttribute('main_spec', $spec ? [
                    'id'       => $spec->id,
                    'name'     => $spec->name,
                    'role'     => $spec->role,
                    'icon_url' => $spec->icon_url,
                ] : null);
            });
        });

        return $users;
    }

    /**
     * Get role counts for a static roster.
     */
    public function getRoleCounts(int $staticId): array
    {
        $users = $this->getStaticMembers($staticId);

        $roleCounts = [
            'tank' => 0,
            'heal' => 0,
            'mdps' => 0,
            'rdps' => 0,
        ];

        foreach ($users as $user) {
            $main = $user->characters->first(
                fn ($char) => $char->statics->first()?->pivot->role === 'main'
            );

            if ($main) {
                $role = $main->main_spec['role'] ?? 'rdps';
                if (isset($roleCounts[$role])) {
                    $roleCounts[$role]++;
                }
            }
        }

        return $roleCounts;
    }

    /**
     * Assign a character to a static with a specific role and handle auto-downgrade.
     */
    public function assignCharacterToStatic(int $characterId, int $staticId, string $role, int $userId): void
    {
        $character = Character::findOrFail($characterId);

        $user = User::findOrFail($userId);
        if (!$user->statics()->where('statics.id', $staticId)->exists()) {
            throw new \Exception('You are not a member of this static.');
        }

        if ($role === 'main') {
            Character::downgradeMainToAlt($userId, $staticId);
        }

        $character->statics()->syncWithoutDetaching([
            $staticId => ['role' => $role],
        ]);

        $this->autoSetMainSpecIfMissing($character, $staticId);
    }

    /**
     * Auto-set main spec from active_spec on first assignment to a static.
     */
    public function autoSetMainSpecIfMissing(Character $character, int $staticId): void
    {
        $hasSpecs = CharacterStaticSpec::where('character_id', $character->id)
            ->where('static_id', $staticId)
            ->exists();

        if ($hasSpecs) {
            return;
        }

        if (!$character->active_spec || !$character->playable_class) {
            return;
        }

        $spec = Specialization::where('name', $character->active_spec)
            ->where('class_name', $character->playable_class)
            ->first();

        if (!$spec) {
            return;
        }

        CharacterStaticSpec::create([
            'character_id' => $character->id,
            'static_id'    => $staticId,
            'spec_id'      => $spec->id,
            'is_main'      => true,
        ]);
    }

    /**
     * Get the overview data for a static roster (mains with their alts).
     */
    public function getRosterOverview(StaticGroup $static): Collection
    {
        $allCharacters = $static->characters()->get();

        $mains = $allCharacters->where(function ($char) {
            return strtolower($char->pivot->role) === 'main';
        })->values();

        foreach ($mains as $main) {
            $main->alts = $allCharacters->where('user_id', $main->user_id)
                ->where('id', '!=', $main->id)
                ->values();
        }

        return $mains;
    }

    /**
     * Transfer ownership of a static group.
     */
    public function transferOwnership(StaticGroup $static, User $currentOwner, User $newOwner): void
    {
        $static->members()->updateExistingPivot($currentOwner->id, [
            'role'        => 'officer',
            'access_role' => 'officer',
        ]);

        $static->members()->updateExistingPivot($newOwner->id, [
            'role'        => 'owner',
            'access_role' => 'leader',
        ]);

        $static->update(['owner_id' => $newOwner->id]);
    }

    /**
     * Kick a member from the static group.
     */
    public function kickMember(StaticGroup $static, User $user): void
    {
        $characterIds = $user->characters()->pluck('id');

        if ($characterIds->isNotEmpty()) {
            $static->characters()->detach($characterIds);
        }

        $static->members()->detach($user->id);
    }

    /**
     * Get roster members with their characters.
     */
    public function getMembersWithCharacters(StaticGroup $static): EloquentCollection
    {
        return $static->members()
            ->with('characters')
            ->get();
    }

    /**
     * Update access role for a member.
     */
    public function updateAccessRole(StaticGroup $static, User $user, string $accessRole): void
    {
        $static->members()->updateExistingPivot($user->id, [
            'access_role' => $accessRole,
        ]);
    }

    /**
     * Update roster status for a member.
     */
    public function updateRosterStatus(StaticGroup $static, User $user, string $rosterStatus): void
    {
        $static->members()->updateExistingPivot($user->id, [
            'roster_status' => $rosterStatus,
        ]);
    }

    /**
     * Update user participation for a static.
     */
    public function updateUserParticipation(User $user, StaticGroup $static, ?int $mainCharId, array $raidingCharIds): void
    {
        $userCharacterIds = Character::belongingTo($user->id)->pluck('id');

        $static->characters()->detach($userCharacterIds);

        foreach ($raidingCharIds as $charId) {
            if ($charId != $mainCharId) {
                $character = Character::find($charId);
                if (!$character) {
                    continue;
                }
                $static->characters()->attach($charId, ['role' => 'alt']);
                $this->autoSetMainSpecIfMissing($character, $static->id);
            }
        }

        if ($mainCharId) {
            $character = Character::find($mainCharId);
            if ($character) {
                $static->characters()->attach($mainCharId, ['role' => 'main']);
                $this->autoSetMainSpecIfMissing($character, $static->id);
            }
        }

        $this->dispatchSyncForAttachedCharacters($mainCharId, $raidingCharIds);
    }

    /**
     * Dispatch data sync jobs for all characters attached to a static.
     */
    private function dispatchSyncForAttachedCharacters(?int $mainCharId, array $raidingCharIds): void
    {
        $characterIds = array_unique(array_filter(
            $mainCharId ? array_merge($raidingCharIds, [$mainCharId]) : $raidingCharIds
        ));

        $characters = Character::whereIn('id', $characterIds)->get();

        foreach ($characters as $character) {
            FetchBnetRawDataJob::dispatch($character);
            FetchRioRawDataJob::dispatch($character);
        }
    }
}
