<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sync Intervals (minutes) per Plan Tier
    |--------------------------------------------------------------------------
    |
    | Defines how often each service is synced for each subscription tier.
    | Override via environment variables for testing (e.g. set to 1 for 1-min).
    |
    */
    'intervals' => [
        'free' => [
            'bnet' => (int) env('SYNC_INTERVAL_FREE_BNET', 60),    // 1h
            'rio'  => (int) env('SYNC_INTERVAL_FREE_RIO', 60),     // 1h
            'wcl'  => (int) env('SYNC_INTERVAL_FREE_WCL', 60),     // 1h
        ],
        'premium' => [
            'bnet' => (int) env('SYNC_INTERVAL_PREMIUM_BNET', 60), // 1h
            'rio'  => (int) env('SYNC_INTERVAL_PREMIUM_RIO', 60),  // 1h
            'wcl'  => (int) env('SYNC_INTERVAL_PREMIUM_WCL', 60),  // 1h
        ],
        'pro' => [
            'bnet' => (int) env('SYNC_INTERVAL_PRO_BNET', 15),     // 15m
            'rio'  => (int) env('SYNC_INTERVAL_PRO_RIO', 15),      // 15m
            'wcl'  => (int) env('SYNC_INTERVAL_PRO_WCL', 15),      // 15m
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget Tick Interval (milliseconds)
    |--------------------------------------------------------------------------
    |
    | How often the SyncStatusWidget re-calculates progress and countdown.
    | Default: 1000ms (1 second) — smooth countdown animation.
    |
    */
    'widget_tick_ms' => (int) env('SYNC_WIDGET_TICK_MS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Queue Names
    |--------------------------------------------------------------------------
    |
    | Each service fetches on its own queue so they never block each other.
    |
    */
    'queues' => [
        'bnet'    => env('SYNC_QUEUE_BNET', 'bnet'),
        'rio'     => env('SYNC_QUEUE_RIO', 'rio'),
        'wcl'     => env('SYNC_QUEUE_WCL', 'wcl'),
        'compile' => env('SYNC_QUEUE_COMPILE', 'compile'),
    ],

];
