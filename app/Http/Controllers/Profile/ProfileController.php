<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;

use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\ProfilePrivacyRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Auth\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $payload = $this->userService->buildProfilePayload($request->user());

        return view('profile.edit', $payload);
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
     * Update the user's privacy preferences.
     */
    public function updatePrivacy(ProfilePrivacyRequest $request): RedirectResponse
    {
        $this->userService->updatePrivacyPreferences($request->user(), $request->validated());

        return Redirect::route('profile.edit')->with('status', 'privacy-updated');
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
}
