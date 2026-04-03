<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportGuildRequest;
use App\Http\Requests\StoreStaticRequest;
use App\Services\StaticGroup\StaticService;
use App\Models\StaticGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StaticController extends Controller
{
    protected StaticService $staticService;

    public function __construct(StaticService $staticService)
    {
        $this->staticService = $staticService;
    }

    public function generateInvite(StaticGroup $static): JsonResponse
    {
        // Permission check (only owner or admin can generate link)
        // For simplicity, we allow all members for now, but role check is better

        $link = $this->staticService->getInviteLink($static);

        return response()->json([
            'link' => $link
        ]);
    }

    /**
     * Show the view to choose or create a static.
     */
    public function index(): View
    {
        $data = $this->staticService->buildSetupPayload(
            Auth::id(),
            (string) session('battlenet_token')
        );

        return view('statics.setup', $data);
    }

    /**
     * Store a newly created static in storage.
     */
    public function store(StoreStaticRequest $request): RedirectResponse
    {
        $this->staticService->executeCreation($request->validated(), Auth::id());

        return redirect()->route('dashboard')->with('success', 'Static created successfully!');
    }

    /**
     * Import a guild as a static.
     */
    public function importGuild(ImportGuildRequest $request): RedirectResponse
    {
        $token = session('battlenet_token');
        if (!$token) {
            return back()->with('error', 'Session expired. Please log in again.');
        }

        $this->staticService->executeGuildImport($request->validated(), Auth::id());

        return redirect()->route('dashboard')->with('success', 'Guild imported as Static!');
    }
}
