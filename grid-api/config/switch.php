<?php

return [
    'base_url' => env('SWITCH_BASE_URL', 'http://switch:8000/v2'),
    'api_key' => env('SWITCH_API_KEY'),
    'timeout' => (float) env('SWITCH_TIMEOUT', 10),
];
