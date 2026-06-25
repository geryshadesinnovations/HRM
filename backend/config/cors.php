<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
| The Next.js frontend (a browser SPA) calls this API from a different origin
| (e.g. http://localhost:3000 → http://localhost:8000), so CORS must allow it.
|
| Auth uses a Bearer token (not cookies), so wildcard origins are safe. For
| production, tighten it by setting CORS_ALLOWED_ORIGINS to a comma-separated
| list (e.g. "https://app.yourdomain.com").
*/

$allowed = env('CORS_ALLOWED_ORIGINS');

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowed ? array_map('trim', explode(',', $allowed)) : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer-token auth does not use cookies; keep this false (required when
    // allowed_origins is '*').
    'supports_credentials' => false,
];
