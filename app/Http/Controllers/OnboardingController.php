<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $userCharacters = $user->characters;

        $isGuildMaster = false;
        $guildName = null;

        $gmCharacter = $userCharacters->first(function ($character) {
             return property_exists($character, 'guild_rank') && $character->guild_rank === 0;
        });

        if ($gmCharacter) {
            $isGuildMaster = true;
            $guildName = $gmCharacter->guild_name ?? 'Unknown Guild';
        }

        return view('onboarding.index', [
            'isGuildMaster' => $isGuildMaster,
            'guildName' => $guildName,
            'userCharacters' => $userCharacters,
        ]);
    }

    public function createStatic(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'region' => 'required|string',
        ]);

        $static = StaticGroup::create([
            'name' => $validated['name'],
            'region' => $validated['region'],
            'owner_id' => auth()->id(),
            'invite_token' => Str::random(12),
            'slug' => Str::slug($validated['name']),
        ]);

        // Attach user to static as owner
        $static->members()->attach(auth()->id(), ['role' => 'owner']);

        return redirect()->route('characters.index');
    }
}
