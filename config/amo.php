<?php

return [
    'bootstrap' => [
        'enabled' => (bool) env('AMO_BOOTSTRAP_ENABLED', false),
        'name' => env('AMO_BOOTSTRAP_NAME', 'Первый клиент'),
        'base_domain' => env('AMO_BOOTSTRAP_BASE_DOMAIN'),
        'access_token' => env('AMO_BOOTSTRAP_ACCESS_TOKEN'),
        'token_type' => env('AMO_BOOTSTRAP_TOKEN_TYPE', 'long_lived'),
    ],
    'api_log_retention_days' => (int) env('API_LOG_RETENTION_DAYS', 30),
];
