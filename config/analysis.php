<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Raid Trends (Premium-tier feature)
    |--------------------------------------------------------------------------
    |
    | When enabled, the analyzer pulls historical encounter snapshots and computes
    | per-player and per-boss trends, surfacing them in the AI report. Currently
    | gated by config; will be replaced with a per-static subscription check once
    | billing is in place.
    */
    'cross_raid_trends_enabled' => (bool) env('CROSS_RAID_TRENDS_ENABLED', true),
];
