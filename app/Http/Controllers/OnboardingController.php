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

        // Based on instructions: "Look for a character where they belong to a guild and their guild rank is 0"
        // Since character model might not have these fields yet, we'll try to find them from character data or use defaults.

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
            'userCharacters' => $userCharacters
        ]);
    }

    public function createStatic(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'faction' => 'required|string',
            'region' => 'required|string',
            'server' => 'required|string',
            'character_id' => 'required|exists:characters,id',
        ]);

        $static = StaticGroup::create([
            'name' => $validated['name'],
            'region' => $validated['region'],
            'server' => $validated['server'],
            'owner_id' => auth()->id(),
            'invite_token' => Str::random(12),
            'slug' => Str::slug($validated['name']),
        ]);

        // Attach user to static as admin
        $static->members()->attach(auth()->id(), ['role' => 'admin']);

        // Attach selected character to static
        $static->characters()->attach($validated['character_id'], ['role' => 'owner']);

        return redirect()->route('dashboard');
    }
}
