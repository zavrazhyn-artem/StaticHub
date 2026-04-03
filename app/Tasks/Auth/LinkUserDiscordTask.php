<?php

declare(strict_types=1);

namespace App\Tasks\Auth;

use App\Models\User;

class LinkUserDiscordTask
{
    /**
     * Link Discord account.
     */
    public function run(User $user, object $discordUser): void
    {
        $user->update([
            'discord_id' => $discordUser->id,
            'discord_username' => $discordUser->nickname ?? $discordUser->name,
        ]);
    }
}
