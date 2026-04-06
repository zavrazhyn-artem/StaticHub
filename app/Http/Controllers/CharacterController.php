<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignCharacterRequest;
use App\Services\Character\CharacterService;
use App\Services\Character\CharacterSyncService;
use App\Services\StaticGroup\RosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CharacterController extends Controller
{
    public function __construct(
        protected CharacterSyncService $characterSyncService,
        protected RosterService        $rosterService,
        protected CharacterService     $characterService
    ) {}

    /**
     * Display a listing of the user's characters and the sync button.
     *
     * @return View
     */
    public function index(): View
    {
        $data = $this->characterService->buildIndexPayload(Auth::id());

        return view('characters.index', $data);
    }

    /**
     * Display personal tactical reports.
     *
     * @return View
     */
    public function personalReports(): View
    {
        $reports = $this->characterService->getPersonalReports(Auth::user());

        return view('characters.personal_reports', compact('reports'));
    }

    /**
     * Import characters from Blizzard API.
     *
     * @return RedirectResponse
     */
    public function import(): RedirectResponse
    {
        $token = session('battlenet_token');

        if (!$token) {
            return redirect()->route('dashboard')->with('error', __('Battle.net token not found. Please log in again.'));
        }

        try {
            $this->characterSyncService->syncUserCharacters($token, Auth::id());

            return redirect()->back()->with('success', __('Characters synced successfully!'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to sync characters:') . ' ' . $e->getMessage());
        }
    }

    /**
     * Assign a character to a static with a specific role.
     *
     * @param AssignCharacterRequest $request
     * @return RedirectResponse
     */
    public function assignToStatic(AssignCharacterRequest $request): RedirectResponse
    {
        try {
            $this->rosterService->assignCharacterToStatic(
                $request->integer('character_id'),
                $request->integer('static_id'),
                $request->input('role'),
                Auth::id()
            );

            return redirect()->back()->with('success', __('Character assigned to static successfully!'));
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                abort(403);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
