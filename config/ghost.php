<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ghost Mode
    |--------------------------------------------------------------------------
    |
    | Read-only impersonation used by site admins to peek at any static group
    | as if they were a member. Only the user whose id matches GHOST_USER_ID
    | can enter ghost mode, and only after they've passed the admin access
    | key gate. All mutations are blocked while ghost mode is active.
    |
    */

    'user_id' => env('GHOST_USER_ID'),
];
