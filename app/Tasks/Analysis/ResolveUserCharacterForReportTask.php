<?php

declare(strict_types=1);

namespace App\Tasks\Analysis;

use App\Models\Character;
use App\Models\User;

class ResolveUserCharacterForReportTask
{
    /**
     * Resolve the character context for a user in a report.
     */
    public function run(User $user, int $staticId, int $reportId): ?Character
    {
        $character = Character::query()->findUserCharacterInReport($user->id, $staticId, $reportId);

        if (!$character) {
            $character = $user->getMainCharacterForStatic($staticId);
        }

        return $character;
    }
}
