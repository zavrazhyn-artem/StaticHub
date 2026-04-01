<?php

namespace App\Http\Controllers;

use App\Services\JoinStaticService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class JoinStaticController extends Controller
{
    public function __construct(
        protected JoinStaticService $joinStaticService
    ) {}

    /**
     * Show the join page for a static group.
     *
     * @param string $token
     * @return View
     */
    public function showJoinPage(string $token): View
    {
        $data = $this->joinStaticService->getJoinPageData($token, Auth::id());

        return view('statics.join', $data);
    }

    /**
     * Process a user joining a static group.
     *
     * @param Request $request
     * @param string $token
     * @return RedirectResponse
     */
    public function processJoin(Request $request, string $token): RedirectResponse
    {
        $this->joinStaticService->join($token, Auth::id());

        return redirect()->route('characters.index');
    }
}
