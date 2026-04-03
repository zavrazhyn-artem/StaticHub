<?php

declare(strict_types=1);

namespace App\Tasks\Auth;

use App\Models\User;

class DeleteUserTask
{
    /**
     * Delete user account.
     */
    public function run(User $user): void
    {
        $user->delete();
    }
}
