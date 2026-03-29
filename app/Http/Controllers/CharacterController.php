<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\User;
use App\Services\CharacterSyncService;
use App\Services\RosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CharacterController extends Controller
{
    protected CharacterSyncService $characterSyncService;
    protected RosterService $rosterService;

    public function __construct(CharacterSyncService $characterSyncService, RosterService $rosterService)
    {
        $this->characterSyncService = $characterSyncService;
        $this->rosterService = $rosterService;
    }

    /**
     * Display a listing of the user's characters and the sync button.
     */
    public function index()
    {
        $userId = Auth::id();
        $user = User::where('id', $userId)->withStatics()->first();
        $statics = $user->statics;
        $static = $statics->first(); // Default to first static if exists

        $query = Character::belongingTo($userId)->withStatics();

        if ($static) {
            $query->leftJoin('character_static', function ($join) use ($static) {
                $join->on('characters.id', '=', 'character_static.character_id')
                    ->where('character_static.static_id', '=', $static->id);
            })
            ->select('characters.*', 'character_static.role as static_role')
            ->orderByRaw("CASE WHEN character_static.role = 'main' THEN 0 ELSE 1 END")
            ->orderBy('level', 'desc')
            ->orderBy('equipped_item_level', 'desc');
        } else {
            $query->orderBy('level', 'desc')
                ->orderBy('equipped_item_level', 'desc');
        }

        $characters = $query->get();

        return view('characters.index', compact('characters', 'statics', 'static'));
    }

    /**
     * Import characters from Blizzard API.
     */
    public function import()
    {
        $token = session('battlenet_token');

        if (!$token) {
            return redirect()->route('dashboard')->with('error', 'Battle.net token not found. Please log in again.');
        }

        try {
            $this->characterSyncService->syncUserCharacters($token, Auth::id());

            return redirect()->back()->with('success', 'Characters synced successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to sync characters: ' . $e->getMessage());
        }
    }

    /**
     * Assign a character to a static with a specific role.
     */
    public function assignToStatic(Request $request)
    {
        $validated = $request->validate([
            'character_id' => 'required|exists:characters,id',
            'static_id' => 'required|exists:statics,id',
            'role' => 'required|in:main,alt',
            'combat_role' => 'required|in:tank,heal,mdps,rdps',
        ]);

        try {
            $this->rosterService->assignCharacterToStatic(
                $validated['character_id'],
                $validated['static_id'],
                $validated['role'],
                $validated['combat_role'],
                Auth::id()
            );

            return redirect()->back()->with('success', 'Character assigned to static successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                abort(403);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
