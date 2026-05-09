<?php

declare(strict_types=1);

/**
 * BlastR Desktop bridge release manifest.
 *
 * Source of truth for what `/api/desktop/sync` returns under
 * `manifest.bridge` and `manifest.addon`. Pre-release we hardcode
 * everything; once we ship to a public mirror repo + tag releases via
 * CI, this whole config should move to env-driven values populated by
 * the deploy pipeline.
 *
 * `download_url` set to null means "no update available" — bridge
 * skips self-update entirely. Same applies to addon.
 */

return [
    'bridge' => [
        'latest_version'       => env('BLASTR_BRIDGE_LATEST', '0.2.0-beta'),
        'download_url'         => env('BLASTR_BRIDGE_DOWNLOAD_URL'),
        'sha256'               => env('BLASTR_BRIDGE_SHA256'),
        'min_required_version' => env('BLASTR_BRIDGE_MIN_REQUIRED', '0.2.0-beta'),
        'rollout_pct'          => (int) env('BLASTR_BRIDGE_ROLLOUT_PCT', 100),
        'changelog_url'        => env('BLASTR_BRIDGE_CHANGELOG_URL'),
    ],
    'addon' => [
        'latest_version' => env('BLASTR_ADDON_LATEST', '0.1.55'),
        'download_url'   => env('BLASTR_ADDON_DOWNLOAD_URL'),
        'sha256'         => env('BLASTR_ADDON_SHA256'),
    ],
];
