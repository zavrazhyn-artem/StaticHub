<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class ProfileController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $this->userService->updateProfile($request->user(), $request->validated());

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $this->userService->deleteUser($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Redirect the user to the Discord authentication page.
     */
    public function linkDiscord(): RedirectResponse
    {
        return Socialite::driver('discord')->redirect();
    }

    /**
     * Obtain the user information from Discord.
     */
    public function discordCallback(): RedirectResponse
    {
        try {
            $discordUser = Socialite::driver('discord')->user();

            Auth::user()->update([
                'discord_id' => $discordUser->id,
                'discord_username' => $discordUser->nickname ?? $discordUser->name,
            ]);

            return Redirect::route('profile.edit')->with('status', 'discord-linked');
        } catch (\Exception $e) {
            return Redirect::route('profile.edit')->with('error', 'Failed to link Discord account: ' . $e->getMessage());
        }
    }

    /**
     * Unlink the Discord account.
     */
    public function unlinkDiscord(): RedirectResponse
    {
        Auth::user()->update([
            'discord_id' => null,
            'discord_username' => null,
        ]);

        return Redirect::route('profile.edit')->with('status', 'discord-unlinked');
    }
}
