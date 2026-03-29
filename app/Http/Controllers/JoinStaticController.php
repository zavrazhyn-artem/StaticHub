<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use Illuminate\Http\Request;

class JoinStaticController extends Controller
{
    public function showJoinPage($token)
    {
        $static = StaticGroup::withoutGlobalScope('member')
            ->where('invite_token', $token)
            ->where('invite_until', '>', now())
            ->firstOrFail();
        $userCharacters = auth()->user()->characters;

        return view('statics.join', compact('static', 'userCharacters'));
    }

    public function processJoin(Request $request, $token)
    {
        $static = StaticGroup::withoutGlobalScope('member')
            ->where('invite_token', $token)
            ->where('invite_until', '>', now())
            ->firstOrFail();

        $validated = $request->validate([
            'character_id' => 'required|exists:characters,id,user_id,' . auth()->id(),
        ]);

        // Attach user to static
        if (!$static->members()->where('user_id', auth()->id())->exists()) {
            $static->members()->attach(auth()->id(), ['role' => 'member']);
        }

        // Attach character to static
        if (!$static->characters()->where('character_id', $validated['character_id'])->exists()) {
            $static->characters()->attach($validated['character_id'], ['role' => 'member']);
        }

        return redirect()->route('dashboard');
    }
}
