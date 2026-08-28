<?php

return [
    'base_url' => env('SWITCH_BASE_URL', 'http://switch:8000/v2'),
    'api_key' => env('SWITCH_API_KEY'),
    'timeout' => (float) env('SWITCH_TIMEOUT', 10),
    'cdr_page_size' => (int) env('SWITCH_CDR_PAGE_SIZE', 200),
    'cdr_import_window_days' => (int) env('SWITCH_CDR_IMPORT_WINDOW_DAYS', 7),
    'recording_page_size' => (int) env('SWITCH_RECORDING_PAGE_SIZE', 200),
    'recording_import_window_days' => (int) env('SWITCH_RECORDING_IMPORT_WINDOW_DAYS', 31),
    'fax_page_size' => (int) env('SWITCH_FAX_PAGE_SIZE', 200),
    'fax_import_window_days' => (int) env('SWITCH_FAX_IMPORT_WINDOW_DAYS', 31),
    'line_key_mutations_enabled' => (bool) env('SWITCH_LINE_KEY_MUTATIONS_ENABLED', false),
];
