<?php

declare(strict_types=1);

namespace App\Tasks\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserTask
{
    /**
     * Create a new user.
     */
    public function run(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }
}
