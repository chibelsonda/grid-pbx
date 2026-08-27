<?php

return [
    'base_url' => env('KAZOO_BASE_URL', 'http://kazoo:8000/v2'),
    'api_key' => env('KAZOO_API_KEY'),
    'timeout' => (float) env('KAZOO_TIMEOUT', 10),
];
