<?php

return [
    'call_geography' => [
        'enabled' => (bool) env('DASHBOARD_CALL_GEOGRAPHY_ENABLED', false),
        'source' => env('DASHBOARD_CALL_GEOGRAPHY_SOURCE', 'unconfigured'),
        'maximum_locations' => (int) env('DASHBOARD_CALL_GEOGRAPHY_MAXIMUM_LOCATIONS', 100),
    ],
];
