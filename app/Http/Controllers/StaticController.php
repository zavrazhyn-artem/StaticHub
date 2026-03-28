<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Services\BlizzardApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use App\Models\Realm;

class StaticController extends Controller
{
    protected BlizzardApiService $blizzardApi;

    public function __construct(BlizzardApiService $blizzardApi)
    {
        $this->blizzardApi = $blizzardApi;
    }

    /**
     * Show the view to choose or create a static.
     */
    public function index()
    {
        $user = auth()->user();

        // Use withoutGlobalScope to see ALL available statics if needed,
        // but here we just want to show the ones they already joined.
        $statics = $user->statics;

        // Fetch potential guilds to import
        $token = session('battlenet_token');
        $guilds = [];
        if ($token) {
            $guilds = $this->blizzardApi->getUserGuilds($token);
        }

        // Fetch all realms sorted by name
        $realms = Realm::orderBy('name')->get();

        return view('statics.setup', compact('statics', 'guilds', 'realms'));
    }

    /**
     * Store a newly created static in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'realm_slug' => 'required|string|exists:realms,slug',
            'region' => 'required|string|in:eu,us,kr,tw',
        ]);

        $realm = Realm::where('slug', $request->input('realm_slug'))->firstOrFail();

        DB::transaction(function () use ($request, $realm) {
            $static = StaticGroup::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name . '-' . $realm->slug),
                'server' => $realm->name,
                'region' => $request->region,
                'owner_id' => auth()->id(),
            ]);

            $static->members()->attach(auth()->id(), ['role' => 'owner']);
        });

        return redirect()->route('dashboard')->with('success', 'Static created successfully!');
    }

    /**
     * Import a guild as a static.
     */
    public function importGuild(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'realm_slug' => 'required|string',
            'realm' => 'required|string',
        ]);

        $token = session('battlenet_token');
        if (!$token) {
            return back()->with('error', 'Session expired. Please log in again.');
        }

        // Verify they are the leader (optional but recommended)
        // For this task, we assume they are if they clicked the button from their profile list

        DB::transaction(function () use ($request) {
            $static = StaticGroup::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name . '-' . $request->realm_slug),
                'server' => $request->realm,
                'region' => 'eu', // Defaulting to EU as per project context
                'owner_id' => auth()->id(),
            ]);

            $static->members()->attach(auth()->id(), ['role' => 'owner']);
        });

        return redirect()->route('dashboard')->with('success', 'Guild imported as Static!');
    }
}
