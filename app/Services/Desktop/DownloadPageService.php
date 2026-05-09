<?php

declare(strict_types=1);

namespace App\Services\Desktop;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Builds the props payload for the /desktop download page.
 *
 * Two responsibilities live here:
 *
 *   - Surface the public download manifest (latest version + URL +
 *     SmartScreen warning copy + checksum) so the user can grab the
 *     installer. We keep the same source of truth as
 *     {@see DesktopSyncService}: config('blastr_desktop').
 *
 *   - Detect whether the logged-in user has *already* paired a
 *     bridge — looking at Sanctum personal access tokens issued via
 *     the OAuth device flow. When a paired bridge exists we render
 *     a connection status card instead of pushing the user toward
 *     a redundant second install.
 */
final class DownloadPageService
{
    /**
     * Anything the user issued via the OAuth device flow lives under
     * this token name. If we ever issue PATs from another path we'll
     * need to refine the filter, but for now the device flow is the
     * only producer.
     */
    private const BRIDGE_TOKEN_NAME = 'BlastR Desktop';

    public function __construct(
        private readonly InstallerReleaseService $installer,
        private readonly BridgeReleaseService $bridge,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(User $user): array
    {
        $bridgeCfg      = config('blastr_desktop.bridge');
        $installerReady = $this->installer->exists();
        $bridgeReady    = $this->bridge->exists();

        // Three-tier fallback for the download CTA:
        //   1. Published NSIS installer — preferred path
        //   2. Published portable bridge.exe — works without admin,
        //      no shortcuts/registry but the bridge auto-installs
        //      everything else on first run
        //   3. Env-configured manifest (mostly empty today)
        //   4. null — UI shows "Coming soon"
        if ($installerReady) {
            $manifest = [
                'latest_version' => $this->installer->version(),
                'download_url'   => URL::route('desktop.installer'),
                'sha256'         => $this->installer->sha256(),
                'size_bytes'     => $this->installer->size(),
                'is_portable'    => false,
                'changelog_url'  => $bridgeCfg['changelog_url'] ?? null,
            ];
        } elseif ($bridgeReady) {
            $manifest = [
                'latest_version' => $this->bridge->version(),
                'download_url'   => URL::route('desktop.bridge.portable'),
                'sha256'         => $this->bridge->sha256(),
                'size_bytes'     => $this->bridge->size(),
                'is_portable'    => true,
                'changelog_url'  => $bridgeCfg['changelog_url'] ?? null,
            ];
        } else {
            $manifest = [
                'latest_version' => $bridgeCfg['latest_version'] ?? null,
                'download_url'   => $bridgeCfg['download_url'] ?? null,
                'sha256'         => $bridgeCfg['sha256'] ?? null,
                'size_bytes'     => null,
                'is_portable'    => false,
                'changelog_url'  => $bridgeCfg['changelog_url'] ?? null,
            ];
        }

        return [
            'manifest'         => $manifest,
            'connection'       => $this->resolveConnection($user),
            'onboarding_steps' => $this->onboardingSteps(),
        ];
    }

    /**
     * @return array{paired:bool, last_used_at:?string, token_count:int}
     */
    private function resolveConnection(User $user): array
    {
        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('name', self::BRIDGE_TOKEN_NAME)
            ->get(['last_used_at']);

        if ($tokens->isEmpty()) {
            return ['paired' => false, 'last_used_at' => null, 'token_count' => 0];
        }

        $latest = $tokens
            ->map(fn ($r) => $r->last_used_at ? CarbonImmutable::parse($r->last_used_at) : null)
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'paired'       => true,
            'last_used_at' => $latest?->toIso8601String(),
            'token_count'  => $tokens->count(),
        ];
    }

    /**
     * @return list<array{icon:string, title:string, body:string}>
     */
    private function onboardingSteps(): array
    {
        return [
            [
                'icon'  => 'download',
                'title' => __('Download the bridge'),
                'body'  => __('Save BlastR Desktop to your computer. Windows will warn you because the installer is unsigned — click "More info" then "Run anyway" to continue.'),
            ],
            [
                'icon'  => 'rocket_launch',
                'title' => __('Run the installer'),
                'body'  => __('Launch the .exe. The bridge installs to your AppData folder, places the BlastR addon into your World of Warcraft AddOns directory, and pins itself to your taskbar.'),
            ],
            [
                'icon'  => 'login',
                'title' => __('Sign in via BlastR'),
                'body'  => __('Click "Authorize" in the bridge — your browser opens blastr.pro and asks you to confirm the pairing. Approve once and the bridge stays connected until you log it out.'),
            ],
            [
                'icon'  => 'sync',
                'title' => __('You\'re done'),
                'body'  => __('Wishlists sync into the addon automatically. RCLootCouncil awards push back to blastr.pro within seconds. New bridge releases install themselves quietly in the background.'),
            ],
        ];
    }
}
