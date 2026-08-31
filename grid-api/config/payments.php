<?php

return [
    'enabled' => filter_var(env('PAYMENTS_ENABLED', false), FILTER_VALIDATE_BOOL),
    'provider' => env('PAYMENT_PROVIDER', 'authorize_net'),
    'mutations_enabled' => filter_var(env('PAYMENT_MUTATIONS_ENABLED', false), FILTER_VALIDATE_BOOL),

    'authorize_net' => [
        'environment' => env('AUTHORIZENET_ENVIRONMENT', 'sandbox'),
        'api_login_id' => env('AUTHORIZENET_API_LOGIN_ID'),
        'transaction_key' => env('AUTHORIZENET_TRANSACTION_KEY'),
        'public_client_key' => env('AUTHORIZENET_PUBLIC_CLIENT_KEY'),
        'signature_key' => env('AUTHORIZENET_SIGNATURE_KEY'),
        'sandbox_charge_enabled' => filter_var(
            env('AUTHORIZENET_SANDBOX_CHARGE_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'sandbox_void_enabled' => filter_var(
            env('AUTHORIZENET_SANDBOX_VOID_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'sandbox_refund_enabled' => filter_var(
            env('AUTHORIZENET_SANDBOX_REFUND_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'sandbox_profile_enabled' => filter_var(
            env('AUTHORIZENET_SANDBOX_PROFILE_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'sandbox_max_charge_minor' => (int) env('AUTHORIZENET_SANDBOX_MAX_CHARGE_MINOR', 100),
        'sandbox_max_refund_minor' => (int) env('AUTHORIZENET_SANDBOX_MAX_REFUND_MINOR', 100),
        'accept_ui_url' => 'https://jstest.authorize.net/v3/AcceptUI.js',
        'sandbox_endpoint' => 'https://apitest.authorize.net/xml/v1/request.api',
        'production_endpoint' => 'https://api.authorize.net/xml/v1/request.api',
        'connect_timeout' => 5,
        'timeout' => 10,
    ],
];
