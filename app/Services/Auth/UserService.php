<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;

class UserService
{
    /**
     * Build the payload for the profile edit view.
     */
    public function buildProfilePayload(User $user): array
    {
        $ownedStatics = $user->ownedStatics()->with([
            'members' => fn ($q) => $q->where('user_id', '!=', $user->id)
                ->with(['characters' => fn ($q2) => $q2->whereHas('statics', fn ($q3) => $q3->where('character_static.role', 'main'))]),
        ])->get();

        $transferData = $ownedStatics->map(fn ($static) => [
            'id'      => $static->id,
            'name'    => $static->name,
            'url'     => route('profile.static.transfer', $static),
            'members' => $static->members->map(fn ($member) => [
                'id'        => $member->id,
                'name'      => $member->name,
                'character' => ($char = $member->characters->first()) ? [
                    'name'          => $char->name,
                    'playable_class' => $char->playable_class,
                    'avatar_url'    => $char->avatar_url,
                ] : null,
            ])->values(),
        ])->values();

        return [
            'user'         => $user,
            'ownedStatics' => $ownedStatics,
            'transferData' => $transferData,
        ];
    }

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
     * Update the user's privacy preferences.
     */
    public function updatePrivacyPreferences(User $user, array $data): void
    {
        $user->update([
            'hide_battletag' => (bool) ($data['hide_battletag'] ?? false),
        ]);
    }

    /**
     * Update the user's locale preference.
     */
    public function updateLocale(User $user, string $locale): void
    {
        $user->locale = $locale;
        $user->save();
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
