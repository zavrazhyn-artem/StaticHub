<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\OAuthDeviceException;
use App\Http\Controllers\Controller;
use App\Services\Sync\OAuthDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Browser-side of the device flow:
 *   GET  /oauth/device           → show approval page (auth required, but we
 *                                  bounce guests through Battle.net OAuth and
 *                                  carry the user_code through the round-trip)
 *   POST /oauth/device/approve   → mark code approved
 *   POST /oauth/device/deny      → mark code denied
 *
 * The bridge polls a separate API endpoint for the actual token exchange.
 */
final class OAuthDeviceApprovalController extends Controller
{
    public function __construct(
        private readonly OAuthDeviceService $svc
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $rawUserCode = (string) $request->query('user_code', '');
        $userCode    = $this->svc->normalizeUserCode($rawUserCode);

        if (! Auth::check()) {
            // Round-trip through Battle.net OAuth and come back here with
            // the user_code preserved. BattleNetController honors the
            // `oauth_device_return_to` session key in its callback.
            $request->session()->put(
                'oauth_device_return_to',
                $userCode === '' ? '/oauth/device' : '/oauth/device?user_code=' . $userCode,
            );

            return redirect()->route('battlenet.redirect');
        }

        $code = $userCode === '' ? null : $this->svc->findPendingByUserCode($userCode);

        return view('oauth.device.approve', [
            'user_code'        => $userCode === '' ? '' : $this->svc->formatUserCode($userCode),
            'user_code_raw'    => $userCode,
            'code'             => $code,
            'authenticated_as' => Auth::user(),
        ]);
    }

    public function approve(Request $request): RedirectResponse|View
    {
        $data = $request->validate([
            'user_code' => 'required|string|max:32',
        ]);

        try {
            $this->svc->approve(
                $this->svc->normalizeUserCode($data['user_code']),
                Auth::user(),
            );
        } catch (OAuthDeviceException $e) {
            return back()->withErrors(['user_code' => $e->getMessage()]);
        }

        return view('oauth.device.result', [
            'kind'    => 'approved',
            'message' => __('You can close this tab and return to BlastR Desktop.'),
        ]);
    }

    public function deny(Request $request): RedirectResponse|View
    {
        $data = $request->validate([
            'user_code' => 'required|string|max:32',
        ]);

        try {
            $this->svc->deny(
                $this->svc->normalizeUserCode($data['user_code']),
                Auth::user(),
            );
        } catch (OAuthDeviceException $e) {
            return back()->withErrors(['user_code' => $e->getMessage()]);
        }

        return view('oauth.device.result', [
            'kind'    => 'denied',
            'message' => __('Authorization denied. You can close this tab.'),
        ]);
    }
}
