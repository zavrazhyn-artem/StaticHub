<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\BattleNetAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
        return $this->authService->buildRedirectProvider();
    }

    /**
     * Obtain the user information from Battle.net.
     *
     * @return RedirectResponse
     */
    public function callback(): RedirectResponse
    {
        $data = $this->authService->executeCallbackProcessing();

        $user = $data['user'];
        $token = $data['token'];

        // Store token in session for API calls
        session(['battlenet_token' => $token]);

        Auth::login($user);

        if (User::query()->hasMainCharacter($user->id)) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('characters.index');
    }
}
