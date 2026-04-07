<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\OnboardingRequest;
use App\Services\StaticGroup\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    /**
     * Show the onboarding stepper page.
     */
    public function index(): View
    {
        $data = $this->onboardingService->buildOnboardingPayload(Auth::user());

        return view('onboarding.index', $data);
    }

    /**
     * Create a new static group (AJAX from stepper).
     */
    public function createStatic(OnboardingRequest $request): JsonResponse
    {
        $static = $this->onboardingService->executeStaticCreation(
            $request->validated(),
            Auth::id()
        );

        $characterData = $this->onboardingService->buildCharacterStepPayload(Auth::user(), $static);

        return response()->json([
            'success' => true,
            'static' => [
                'id' => $static->id,
                'name' => $static->name,
                'region' => $static->region,
            ],
            'characterData' => $characterData,
        ]);
    }

    /**
     * Validate an invite token and return static info (AJAX).
     */
    public function validateToken(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $data = $this->onboardingService->validateInviteToken($request->input('token'));

        if (!$data) {
            return response()->json(['valid' => false], 404);
        }

        return response()->json([
            'valid' => true,
            'static' => $data,
        ]);
    }

    /**
     * Join a static group via invite token (AJAX from stepper).
     */
    public function joinStatic(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $result = $this->onboardingService->executeJoin(
            $request->input('token'),
            Auth::user()
        );

        return response()->json([
            'success' => true,
            'static' => [
                'id' => $result['static']->id,
                'name' => $result['static']->name,
                'region' => $result['static']->region,
            ],
            'characterData' => $result['characterData'],
        ]);
    }

    /**
     * Sync characters from Battle.net (AJAX from stepper).
     */
    public function syncCharacters(): JsonResponse
    {
        $result = $this->onboardingService->syncAndBuildCharacterPayload(Auth::user());

        return response()->json($result);
    }

    /**
     * Save character participation (main + alts) during onboarding (AJAX).
     */
    public function saveParticipation(Request $request): JsonResponse
    {
        $request->validate([
            'static_id' => 'required|integer',
            'main_character_id' => 'required|integer',
            'raiding_character_ids' => 'array',
            'raiding_character_ids.*' => 'integer',
        ]);

        $this->onboardingService->saveParticipation(
            Auth::id(),
            $request->input('static_id'),
            $request->input('main_character_id'),
            $request->input('raiding_character_ids', [])
        );

        return response()->json(['success' => true]);
    }
}
