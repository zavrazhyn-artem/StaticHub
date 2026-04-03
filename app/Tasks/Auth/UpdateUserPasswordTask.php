<?php

declare(strict_types=1);

namespace App\Tasks\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUserPasswordTask
{
    /**
     * Update user password.
     */
    public function run(User $user, string $password): void
    {
        $user->update([
            'password' => Hash::make($password),
        ]);
    }
}
