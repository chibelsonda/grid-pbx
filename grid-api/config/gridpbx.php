<?php

return [
    'admin' => [
        'name' => env('GRID_ADMIN_NAME', 'Grid Admin'),
        'email' => env('GRID_ADMIN_EMAIL', 'admin@gridpbx.local'),
        'password' => env('GRID_ADMIN_PASSWORD', 'admin-change-me'),
    ],

    'kazoo_account' => [
        'id' => env('KAZOO_ACCOUNT_ID'),
        'name' => env('KAZOO_ACCOUNT_NAME', 'GridPBX'),
        'realm' => env('KAZOO_ACCOUNT_REALM', 'gridpbx.local'),
    ],
];
