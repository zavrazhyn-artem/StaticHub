<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Update user profile data.
     */
    public function executeUpdateProfile(User $user, array $data): void
    {
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
    }

    /**
     * Register a new user.
     */
    public function executeRegistration(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        return $user;
    }

    /**
     * Delete user account.
     */
    public function executeUserDeletion(User $user): void
    {
        $user->delete();
    }

    /**
     * Link Discord account.
     */
    public function executeDiscordLinking(User $user, object $discordUser): void
    {
        $user->update([
            'discord_id' => $discordUser->id,
            'discord_username' => $discordUser->nickname ?? $discordUser->name,
        ]);
    }

    /**
     * Unlink Discord account.
     */
    public function executeDiscordUnlinking(User $user): void
    {
        $user->update([
            'discord_id' => null,
            'discord_username' => null,
        ]);
    }

    /**
     * Update user password.
     */
    public function executePasswordUpdate(User $user, string $password): void
    {
        $user->update([
            'password' => Hash::make($password),
        ]);
    }
}
