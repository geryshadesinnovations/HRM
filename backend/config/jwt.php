<?php

declare(strict_types=1);

return [
    'secret' => env('JWT_SECRET'),
    'algo' => env('JWT_ALGO', 'HS256'),
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),        // seconds
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 1209600),  // seconds
    'issuer' => env('APP_URL', 'hrms'),
];
