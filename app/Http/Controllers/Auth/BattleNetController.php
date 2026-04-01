<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\BattleNetAuthService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class BattleNetController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  BattleNetAuthService  $authService
     */
    public function __construct(
        protected BattleNetAuthService $authService
    ) {}

    /**
     * Redirect the user to the Battle.net authentication page.
     *
     * @return SymfonyRedirectResponse
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return $this->authService->getRedirectResponse();
    }

    /**
     * Obtain the user information from Battle.net.
     *
     * @return RedirectResponse
     */
    public function callback(): RedirectResponse
    {
        $user = $this->authService->handleCallback();

        if (User::query()->hasMainCharacter($user->id)) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('characters.index');
    }
}
