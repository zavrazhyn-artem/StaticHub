<?php

declare(strict_types=1);

namespace App\Tasks\Auth;

use App\Models\User;

class UnlinkUserDiscordTask
{
    /**
     * Unlink Discord account.
     */
    public function run(User $user): void
    {
        $user->update([
            'discord_id' => null,
            'discord_username' => null,
        ]);
    }
}
