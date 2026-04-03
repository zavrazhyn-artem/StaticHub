<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Tasks\Auth\CreateUserTask;
use App\Tasks\Auth\DeleteUserTask;
use App\Tasks\Auth\LinkUserDiscordTask;
use App\Tasks\Auth\UnlinkUserDiscordTask;
use App\Tasks\Auth\UpdateUserPasswordTask;
use App\Tasks\Auth\UpdateUserProfileTask;
use Illuminate\Auth\Events\Registered;

class UserService
{
    public function __construct(
        protected CreateUserTask $createUserTask,
        protected UpdateUserProfileTask $updateUserProfileTask,
        protected UpdateUserPasswordTask $updateUserPasswordTask,
        protected DeleteUserTask $deleteUserTask,
        protected LinkUserDiscordTask $linkUserDiscordTask,
        protected UnlinkUserDiscordTask $unlinkUserDiscordTask,
    ) {}

    /**
     * Update user profile data.
     */
    public function executeUpdateProfile(User $user, array $data): void
    {
        $this->updateUserProfileTask->run($user, $data);
    }

    /**
     * Register a new user.
     */
    public function executeRegistration(array $data): User
    {
        $user = $this->createUserTask->run($data);

        event(new Registered($user));

        return $user;
    }

    /**
     * Delete user account.
     */
    public function executeUserDeletion(User $user): void
    {
        $this->deleteUserTask->run($user);
    }

    /**
     * Link Discord account.
     */
    public function executeDiscordLinking(User $user, object $discordUser): void
    {
        $this->linkUserDiscordTask->run($user, $discordUser);
    }

    /**
     * Unlink Discord account.
     */
    public function executeDiscordUnlinking(User $user): void
    {
        $this->unlinkUserDiscordTask->run($user);
    }

    /**
     * Update user password.
     */
    public function executePasswordUpdate(User $user, string $password): void
    {
        $this->updateUserPasswordTask->run($user, $password);
    }
}
