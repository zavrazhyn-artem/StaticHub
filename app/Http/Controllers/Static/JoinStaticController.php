<?php

declare(strict_types=1);

namespace App\Http\Controllers\Static;

use App\Http\Controllers\Controller;

use App\Services\StaticGroup\JoinStaticService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class JoinStaticController extends Controller
{
    public function __construct(
        protected JoinStaticService $joinStaticService,
    ) {}

    /**
     * Show public landing page for an invite link.
     * If user is already authenticated and has statics, redirect to legacy join flow.
     */
    public function showLanding(string $token): View|RedirectResponse
    {
        // If already logged in, store token and redirect to onboarding
        if (Auth::check()) {
            session(['pending_join_token' => $token]);
            return redirect()->route('onboarding.index');
        }

        $data = $this->joinStaticService->buildPublicPreview($token);

        // Store token in session so it persists through Battle.net auth
        session(['pending_join_token' => $token]);

        return view('statics.join-landing', $data);
    }

    /**
     * Process a user joining a static group.
     */
    public function processJoin(Request $request, string $token): RedirectResponse
    {
        $userId = (int) Auth::id();

        $this->joinStaticService->executeJoin($token, $userId);

        return redirect()->route('characters.index')->with('success', __('Welcome to the team!'));
    }
}
