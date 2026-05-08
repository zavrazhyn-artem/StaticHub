<?php

declare(strict_types=1);

namespace App\Services\Desktop;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(User $user): array
    {
        $bridge = config('blastr_desktop.bridge');

        return [
            'manifest' => [
                'latest_version' => $bridge['latest_version'] ?? null,
                'download_url'   => $bridge['download_url'] ?? null,
                'sha256'         => $bridge['sha256'] ?? null,
                'changelog_url'  => $bridge['changelog_url'] ?? null,
            ],
            'connection' => $this->resolveConnection($user),
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
