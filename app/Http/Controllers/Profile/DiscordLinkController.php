<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;

use App\Services\Auth\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Laravel\Socialite\Facades\Socialite;

class DiscordLinkController extends Controller
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /**
     * Redirect the user to the Discord authentication page.
     */
    public function link(): RedirectResponse
    {
        return Socialite::driver('discord')->redirect();
    }

    /**
     * Obtain the user information from Discord.
     */
    public function callback(): RedirectResponse
    {
        try {
            $discordUser = Socialite::driver('discord')->user();

            $this->userService->executeDiscordLinking(Auth::user(), $discordUser);

            return Redirect::route('profile.edit')->with('status', 'discord-linked');
        } catch (\Exception $e) {
            return Redirect::route('profile.edit')->with('error', 'Failed to link Discord account: ' . $e->getMessage());
        }
    }

    /**
     * Unlink the Discord account.
     */
    public function unlink(): RedirectResponse
    {
        $this->userService->executeDiscordUnlinking(Auth::user());

        return Redirect::route('profile.edit')->with('status', 'discord-unlinked');
    }
}
