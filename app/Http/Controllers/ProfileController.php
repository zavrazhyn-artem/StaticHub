<?php

namespace App\Http\Controllers;

use App\Actions\StaticGroup\KickStaticMemberAction;
use App\Actions\StaticGroup\TransferStaticOwnershipAction;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\StaticGroup;
use App\Services\Auth\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class ProfileController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

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

        return view('profile.edit', [
            'user'         => $user,
            'ownedStatics' => $ownedStatics,
            'transferData' => $transferData,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $this->userService->executeUpdateProfile($request->user(), $request->validated());

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(DeleteAccountRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $this->userService->executeUserDeletion($user);

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

            $this->userService->executeDiscordLinking(Auth::user(), $discordUser);

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
        $this->userService->executeDiscordUnlinking(Auth::user());

        return Redirect::route('profile.edit')->with('status', 'discord-unlinked');
    }

    /**
     * Transfer ownership of a static group to another member.
     */
    public function transferOwnership(Request $request, StaticGroup $static, TransferStaticOwnershipAction $action): RedirectResponse
    {
        $user = $request->user();

        if ($static->owner_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate(['new_owner_id' => 'required|integer']);

        $newOwner = $static->members()
            ->where('user_id', $validated['new_owner_id'])
            ->firstOrFail();

        $action->execute($static, $user, $newOwner);

        return Redirect::route('profile.edit')->with('status', 'ownership-transferred');
    }

    /**
     * Leave the current static group.
     */
    public function leaveStatic(Request $request, KickStaticMemberAction $kickAction): RedirectResponse
    {
        $user = $request->user();
        $statics = $user->statics()->get();

        foreach ($statics as $static) {
            if ($static->owner_id === $user->id) {
                return Redirect::route('profile.edit')->with('error', 'leave-owner');
            }
            $kickAction->execute($static, $user);
        }

        return Redirect::route('onboarding.index')->with('status', 'left-static');
    }
}
