<?php

namespace App\Http\Controllers;

use App\Services\StaticService;
use App\Models\StaticGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StaticController extends Controller
{
    protected StaticService $staticService;

    public function __construct(StaticService $staticService)
    {
        $this->staticService = $staticService;
    }

    public function generateInvite(StaticGroup $static)
    {
        // Перевірка прав (тільки власник або адмін може генерувати лінку)
        // Для спрощення зараз дозволимо всім членам, але краще перевірити роль

        if ($static->invite_token && $static->invite_until && $static->invite_until->isFuture()) {
            return response()->json([
                'link' => route('statics.join', $static->invite_token)
            ]);
        }

        $static->update([
            'invite_token' => Str::random(12),
            'invite_until' => now()->addDay(), // Дійсна 24 години
        ]);

        return response()->json([
            'link' => route('statics.join', $static->invite_token)
        ]);
    }

    /**
     * Show the view to choose or create a static.
     */
    public function index()
    {
        $data = $this->staticService->getSetupData(
            Auth::id(),
            session('battlenet_token')
        );

        return view('statics.setup', $data);
    }

    /**
     * Store a newly created static in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'realm_slug' => 'required|string|exists:realms,slug',
            'region' => 'required|string|in:eu,us,kr,tw',
        ]);

        $this->staticService->createStatic($validated, Auth::id());

        return redirect()->route('dashboard')->with('success', 'Static created successfully!');
    }

    /**
     * Import a guild as a static.
     */
    public function importGuild(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'realm_slug' => 'required|string',
            'realm' => 'required|string',
        ]);

        $token = session('battlenet_token');
        if (!$token) {
            return back()->with('error', 'Session expired. Please log in again.');
        }

        $this->staticService->importGuild($validated, Auth::id());

        return redirect()->route('dashboard')->with('success', 'Guild imported as Static!');
    }
}
