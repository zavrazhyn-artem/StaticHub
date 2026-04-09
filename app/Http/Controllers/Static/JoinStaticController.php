<?php

declare(strict_types=1);

namespace App\Http\Controllers\Static;

use App\Http\Controllers\Controller;

use App\Services\StaticGroup\JoinStaticService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class JoinStaticController extends Controller
{
    public function __construct(
        protected JoinStaticService $joinStaticService,
    ) {}

    /**
     * Show the join page for a static group.
     */
    public function showJoinPage(string $token): View
    {
        $data = $this->joinStaticService->buildJoinPayload($token, (int) Auth::id());

        return view('statics.join', $data);
    }

    /**
     * Process a user joining a static group.
     */
    public function processJoin(Request $request, string $token): RedirectResponse
    {
        $userId = (int) Auth::id();

        $this->joinStaticService->executeJoin($token, $userId);

        return redirect()->route('characters.index')->with('success', __('Welcome to the team!'));
    }
}
