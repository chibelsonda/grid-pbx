<?php

return [
    'admin' => [
        'name' => env('GRID_ADMIN_NAME', 'Grid Admin'),
        'email' => env('GRID_ADMIN_EMAIL', 'admin@gridpbx.local'),
        'password' => env('GRID_ADMIN_PASSWORD'),
    ],

    'switch_account' => [
        'id' => env('SWITCH_ACCOUNT_ID'),
        'name' => env('SWITCH_ACCOUNT_NAME', 'GridPBX'),
        'realm' => env('SWITCH_ACCOUNT_REALM', 'gridpbx.local'),
        'timezone' => env('SWITCH_ACCOUNT_TIMEZONE', 'UTC'),
    ],
];
