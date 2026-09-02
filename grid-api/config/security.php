<?php

return [
    'rate_limits' => [
        'login_credentials_per_minute' => (int) env('RATE_LIMIT_LOGIN_CREDENTIALS_PER_MINUTE', 5),
        'login_account_per_minute' => (int) env('RATE_LIMIT_LOGIN_ACCOUNT_PER_MINUTE', 20),
        'login_ip_per_minute' => (int) env('RATE_LIMIT_LOGIN_IP_PER_MINUTE', 50),
        'api_user_per_minute' => (int) env('RATE_LIMIT_API_USER_PER_MINUTE', 120),
        'api_ip_per_minute' => (int) env('RATE_LIMIT_API_IP_PER_MINUTE', 600),
        'mutation_user_per_minute' => (int) env('RATE_LIMIT_MUTATION_USER_PER_MINUTE', 60),
        'sync_user_per_minute' => (int) env('RATE_LIMIT_SYNC_USER_PER_MINUTE', 6),
        'expensive_user_per_minute' => (int) env('RATE_LIMIT_EXPENSIVE_USER_PER_MINUTE', 30),
        'upload_user_per_minute' => (int) env('RATE_LIMIT_UPLOAD_USER_PER_MINUTE', 10),
        'sensitive_mutation_per_minute' => (int) env('RATE_LIMIT_SENSITIVE_MUTATION_PER_MINUTE', 6),
        'webhook_ip_per_minute' => (int) env('RATE_LIMIT_WEBHOOK_IP_PER_MINUTE', 120),
        'global_search_per_minute' => (int) env('RATE_LIMIT_GLOBAL_SEARCH_PER_MINUTE', 30),
        'billing_documents_per_minute' => (int) env('RATE_LIMIT_BILLING_DOCUMENTS_PER_MINUTE', 30),
        'payment_sandbox_per_minute' => (int) env('RATE_LIMIT_PAYMENT_SANDBOX_PER_MINUTE', 3),
    ],

    'request_size' => [
        'api_bytes' => (int) env('API_MAX_BODY_BYTES', 1048576),
        'upload_bytes' => (int) env('API_UPLOAD_MAX_BODY_BYTES', 12582912),
        'webhook_bytes' => (int) env('AUTHORIZENET_WEBHOOK_MAX_BODY_BYTES', 65536),
    ],

    'headers' => [
        'hsts' => filter_var(env('SECURITY_HSTS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    ],
];
