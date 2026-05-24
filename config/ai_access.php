<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Tester Access Gate
    |--------------------------------------------------------------------------
    |
    | Secret that unlocks the AI-tester login gate (GET /auth/ai-tester),
    | which establishes an authenticated session WITHOUT Battle.net OAuth so
    | automated testing agents can drive the app locally. When this key is
    | empty the gate route is never registered (404), and the gate is hard-
    | blocked in production regardless. NEVER set this in production.
    |
    */

    'key' => env('AI_ACCESS'),
];
