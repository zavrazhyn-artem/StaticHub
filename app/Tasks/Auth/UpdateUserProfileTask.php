<?php

declare(strict_types=1);

namespace App\Tasks\Auth;

use App\Models\User;

class UpdateUserProfileTask
{
    /**
     * Update user profile data.
     */
    public function run(User $user, array $data): void
    {
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
    }
}
