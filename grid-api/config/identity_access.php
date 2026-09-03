<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    'password' => [
        'minimum_length' => 12,
        'maximum_length' => 128,
    ],

    'rate_limits' => [
        'forgot_password' => (int) env('AUTH_FORGOT_PASSWORD_RATE_LIMIT', 5),
        'reset_password' => (int) env('AUTH_RESET_PASSWORD_RATE_LIMIT', 5),
    ],
];
