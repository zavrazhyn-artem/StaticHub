<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Services\Desktop\BridgeReleaseService;
use App\Services\Desktop\DownloadPageService;
use App\Services\Desktop\InstallerReleaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public landing for the BlastR Desktop bridge installer.
 *
 * Renders the marketing/onboarding page that ships the .exe link and
 * the SmartScreen workaround instructions. Once we ship to the
 * Microsoft Store, this controller will also surface the Store badge
 * as the recommended path.
 */
final class DownloadController extends Controller
{
    public function __construct(
        private readonly DownloadPageService $svc,
        private readonly InstallerReleaseService $installer,
        private readonly BridgeReleaseService $bridge,
    ) {}

    public function index(): View
    {
        return view('desktop.index', [
            'payload' => $this->svc->buildPayload(Auth::user()),
        ]);
    }

    /**
     * Public installer download — no auth gate. This is the
     * bootstrap path: brand-new users haven't paired with anything,
     * so they can't carry a token. Bridge auto-update flows through
     * /api/desktop/bridge under sanctum auth instead.
     */
    public function installer(): BinaryFileResponse
    {
        if (! $this->installer->exists()) {
            abort(404, 'Installer is not yet published');
        }
        return $this->installer->streamResponse();
    }

    /**
     * Public portable-bridge download — surfaced on /desktop when no
     * NSIS installer is published. Same exe as /api/desktop/bridge but
     * without the sanctum gate, since a brand-new user has no token to
     * present yet.
     */
    public function portableBridge(): BinaryFileResponse
    {
        if (! $this->bridge->exists()) {
            abort(404, 'Bridge is not yet published');
        }
        return $this->bridge->streamResponse();
    }
}
