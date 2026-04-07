<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schedule Generation Horizon (days)
    |--------------------------------------------------------------------------
    |
    | How many days ahead to auto-generate raid events.
    | Used by both the cron command and dynamic schedule settings.
    |
    */

    'schedule_days_ahead' => (int) env('RAID_SCHEDULE_DAYS_AHEAD', 30),

];
