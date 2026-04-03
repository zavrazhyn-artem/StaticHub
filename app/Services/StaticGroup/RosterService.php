<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\StaticGroup;
use App\Models\User;
use App\Tasks\StaticGroup\AssignCharacterRoleTask;
use App\Tasks\StaticGroup\FetchRosterOverviewTask;
use App\Tasks\StaticGroup\FetchStaticRosterTask;
use App\Tasks\StaticGroup\SyncUserParticipationTask;
use Illuminate\Support\Collection;

class RosterService
{
    public function __construct(
        private readonly FetchStaticRosterTask $fetchStaticRosterTask,
        private readonly FetchRosterOverviewTask $fetchRosterOverviewTask,
        private readonly AssignCharacterRoleTask $assignCharacterRoleTask,
        private readonly SyncUserParticipationTask $syncUserParticipationTask
    ) {
    }

    /**
     * Get all members (users) of a static with their characters, grouped by role.
     *
     * @param int $staticId
     * @return Collection
     */
    public function getGroupedRoster(int $staticId): Collection
    {
        $users = $this->getStaticMembers($staticId);

        $users->each(function (User $user) use ($staticId) {
            $user->setRelation('mainCharacter', $user->getMainCharacterForStatic($staticId));
            $user->setRelation('altCharacters', $user->getAltCharactersForStatic($staticId));
        });

        return $users->groupBy(function (User $user) use ($staticId) {
            $main = $user->mainCharacter;
            return $main ? $main->getCombatRoleInStatic($staticId) : 'unknown';
        });
    }

    /**
     * Get all members (users) of a static with their characters.
     *
     * @param int $staticId
     * @return Collection
     */
    public function getStaticMembers(int $staticId): Collection
    {
        return $this->fetchStaticRosterTask->run($staticId);
    }

    /**
     * Get role counts for a static roster.
     *
     * @param int $staticId
     * @return array
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
            $main = $user->getMainCharacterForStatic($staticId);
            if ($main) {
                $role = $main->getCombatRoleInStatic($staticId);
                if (isset($roleCounts[$role])) {
                    $roleCounts[$role]++;
                }
            }
        }

        return $roleCounts;
    }

    /**
     * Assign a character to a static with a specific role and handle auto-downgrade.
     *
     * @param int $characterId
     * @param int $staticId
     * @param string $role
     * @param string $combatRole
     * @param int $userId
     * @return void
     */
    public function assignCharacterToStatic(int $characterId, int $staticId, string $role, string $combatRole, int $userId): void
    {
        $this->assignCharacterRoleTask->run($characterId, $staticId, $role, $combatRole, $userId);
    }

    /**
     * Get the overview data for a static roster (mains with their alts).
     *
     * @param StaticGroup $static
     * @return Collection
     */
    public function getRosterOverview(StaticGroup $static): Collection
    {
        return $this->fetchRosterOverviewTask->run($static);
    }

    /**
     * Update user participation for a static.
     *
     * @param User $user
     * @param StaticGroup $static
     * @param int|null $mainCharId
     * @param array $raidingCharIds
     * @param array $combatRoles
     * @return void
     */
    public function updateUserParticipation(User $user, StaticGroup $static, ?int $mainCharId, array $raidingCharIds, array $combatRoles): void
    {
        $this->syncUserParticipationTask->run($user, $static, $mainCharId, $raidingCharIds, $combatRoles);
    }
}
