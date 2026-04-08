<?php

namespace App\Http\Controllers\Static;

use App\Http\Controllers\Controller;

use App\Http\Requests\ImportGuildRequest;
use App\Http\Requests\StoreStaticRequest;
use App\Services\InviteCode\InviteCodeService;
use App\Services\StaticGroup\StaticService;
use App\Models\StaticGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaticController extends Controller
{
    public function __construct(
        protected StaticService $staticService,
        protected InviteCodeService $inviteCodeService,
    ) {}

    public function generateInvite(StaticGroup $static): JsonResponse
    {
        Gate::authorize('manage', $static);

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
        $this->inviteCodeService->validate($request->input('invite_code'));

        $this->staticService->executeCreation($request->validated(), Auth::id());

        $this->inviteCodeService->redeem($request->input('invite_code'), Auth::id());

        return redirect()->route('dashboard')->with('success', __('Static created successfully!'));
    }

    /**
     * Import a guild as a static.
     */
    public function importGuild(ImportGuildRequest $request): RedirectResponse
    {
        $token = session('battlenet_token');
        if (!$token) {
            return back()->with('error', __('Session expired. Please log in again.'));
        }

        $this->staticService->executeGuildImport($request->validated(), Auth::id());

        return redirect()->route('dashboard')->with('success', __('Guild imported as Static!'));
    }
}
