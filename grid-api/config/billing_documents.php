<?php

return [
    'invoices' => [
        'provider' => env('BILLING_INVOICE_PROVIDER', 'unconfigured'),
    ],
    'receipts' => [
        'provider' => env('BILLING_RECEIPT_PROVIDER', 'unconfigured'),
    ],
    'downloads' => [
        'maximum_bytes' => (int) env('BILLING_DOCUMENT_MAXIMUM_BYTES', 10 * 1024 * 1024),
    ],
    'legacy_gridpbx' => [
        'enabled' => filter_var(env('BILLING_LEGACY_ENABLED', false), FILTER_VALIDATE_BOOL),
        'authority_confirmed' => filter_var(
            env('BILLING_LEGACY_AUTHORITY_CONFIRMED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'read_only_confirmed' => filter_var(
            env('BILLING_LEGACY_READ_ONLY_CONFIRMED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'connection' => 'legacy_billing',
        'detail_lookup_limit' => (int) env('BILLING_LEGACY_DETAIL_LOOKUP_LIMIT', 500),
    ],
];
