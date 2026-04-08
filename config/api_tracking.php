<?php

return [
    'services' => [
        'blizzard' => [
            'enabled' => env('API_TRACKING_BLIZZARD', true),
            'log_success' => true,
            'log_errors' => true,
        ],
        'wcl' => [
            'enabled' => env('API_TRACKING_WCL', true),
            'log_success' => true,
            'log_errors' => true,
        ],
        'raiderio' => [
            'enabled' => env('API_TRACKING_RAIDERIO', true),
            'log_success' => true,
            'log_errors' => true,
        ],
        'gemini' => [
            'enabled' => env('API_TRACKING_GEMINI', true),
            'log_success' => true,
            'log_errors' => true,
        ],
    ],

    'retention_days' => (int) env('API_TRACKING_RETENTION_DAYS', 7),
];
