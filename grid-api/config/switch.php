<?php

return [
    'base_url' => env('SWITCH_BASE_URL', 'http://switch:8000/v2'),
    'provisioner_url' => env('SWITCH_PROVISIONER_URL'),
    'provisioner' => [
        'auth_type' => env('SWITCH_PROVISIONER_AUTH_TYPE', 'none'),
        'token' => env('SWITCH_PROVISIONER_TOKEN'),
        'username' => env('SWITCH_PROVISIONER_USERNAME'),
        'password' => env('SWITCH_PROVISIONER_PASSWORD'),
        'header_name' => env('SWITCH_PROVISIONER_HEADER_NAME', 'X-Auth-Token'),
        'timeout' => (float) env('SWITCH_PROVISIONER_TIMEOUT', 10),
        'verify_tls' => filter_var(env('SWITCH_PROVISIONER_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),
    ],
    'api_key' => env('SWITCH_API_KEY'),
    'timeout' => (float) env('SWITCH_TIMEOUT', 10),
    'cdr_page_size' => (int) env('SWITCH_CDR_PAGE_SIZE', 200),
    'cdr_import_window_days' => (int) env('SWITCH_CDR_IMPORT_WINDOW_DAYS', 7),
    'recording_page_size' => (int) env('SWITCH_RECORDING_PAGE_SIZE', 200),
    'recording_import_window_days' => (int) env('SWITCH_RECORDING_IMPORT_WINDOW_DAYS', 31),
    'fax_page_size' => (int) env('SWITCH_FAX_PAGE_SIZE', 200),
    'fax_import_window_days' => (int) env('SWITCH_FAX_IMPORT_WINDOW_DAYS', 31),
    'extension_polling' => [
        'enabled' => filter_var(env('SWITCH_EXTENSION_POLLING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'interval_minutes' => (int) env('SWITCH_EXTENSION_POLL_INTERVAL_MINUTES', 15),
        'batch_size' => (int) env('SWITCH_EXTENSION_POLL_BATCH_SIZE', 10),
    ],
    'line_key_mutations_enabled' => (bool) env('SWITCH_LINE_KEY_MUTATIONS_ENABLED', false),
    'conference_participant_token_ttl' => (int) env('SWITCH_CONFERENCE_PARTICIPANT_TOKEN_TTL', 300),
];
