<?php

return [
    'access_key' => env('ADMIN_ACCESS_KEY'),
    'subdomain' => env('ADMIN_SUBDOMAIN', 'admin'),
    // Full admin hostname override. When set, takes precedence over the
    // {subdomain}.{app_host} composition — useful when the app host itself
    // is already a subdomain and the wildcard cert only covers one level.
    'domain' => env('ADMIN_DOMAIN'),
];
