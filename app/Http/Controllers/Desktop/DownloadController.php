<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Services\Desktop\DownloadPageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
    ) {}

    public function index(): View
    {
        return view('desktop.index', [
            'payload' => $this->svc->buildPayload(Auth::user()),
        ]);
    }
}
