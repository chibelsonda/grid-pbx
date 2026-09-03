<?php

$frontendOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', env('FRONTEND_URLS', env('FRONTEND_URL', 'http://localhost:5173'))),
)));
$localOriginPatterns = env('APP_ENV', 'production') === 'local'
    ? ['#^https?://(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$#']
    : [];

return [
    'paths' => [
        'api/*',
        'login',
        'logout',
        'forgot-password',
        'reset-password',
        'sanctum/csrf-cookie',
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => $frontendOrigins,
    'allowed_origins_patterns' => $localOriginPatterns,
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Request-ID'],
    'max_age' => 0,
    'supports_credentials' => true,
];
