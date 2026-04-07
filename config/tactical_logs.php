<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Manual Log Upload Cooldown (minutes)
    |--------------------------------------------------------------------------
    |
    | How many minutes must pass between manual log uploads per static group.
    | Keyed by subscription tier for future expansion.
    |
    */

    'manual_cooldown_minutes' => [
        'free' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Fetch Delay (minutes)
    |--------------------------------------------------------------------------
    |
    | Default number of minutes to wait after a raid ends before fetching
    | logs from WCL. Overridden per-static via settings.
    |
    */

    'auto_fetch_delay_minutes' => 30,

];
