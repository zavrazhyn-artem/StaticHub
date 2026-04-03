<?php

namespace App\Http\Controllers;

use App\Http\Requests\OnboardingRequest;
use App\Services\StaticGroup\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    /**
     * Show the onboarding index page.
     */
    public function index(): View
    {
        $data = $this->onboardingService->buildOnboardingPayload(Auth::user());

        return view('onboarding.index', $data);
    }

    /**
     * Create a new static group.
     */
    public function createStatic(OnboardingRequest $request): RedirectResponse
    {
        $this->onboardingService->executeStaticCreation($request->validated(), Auth::id());

        return redirect()->route('characters.index');
    }
}
