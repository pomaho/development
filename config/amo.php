<?php

return [
    'bootstrap' => [
        'enabled' => (bool) env('AMO_BOOTSTRAP_ENABLED', false),
        'name' => env('AMO_BOOTSTRAP_NAME', 'Первый клиент'),
        'base_domain' => env('AMO_BOOTSTRAP_BASE_DOMAIN'),
        'access_token' => env('AMO_BOOTSTRAP_ACCESS_TOKEN'),
        'token_type' => env('AMO_BOOTSTRAP_TOKEN_TYPE', 'long_lived'),
    ],
    'external' => [
        'name' => env('AMO_EXTERNAL_INTEGRATION_NAME', env('APP_NAME', 'amo Integrator Hub')),
        'description' => env('AMO_EXTERNAL_INTEGRATION_DESCRIPTION', 'OAuth-подключение amoCRM к интеграторскому сервису.'),
        'logo_url' => env('AMO_EXTERNAL_INTEGRATION_LOGO_URL'),
        'scopes' => array_values(array_filter(array_map(
            static fn (string $scope): string => trim($scope),
            explode(',', env('AMO_EXTERNAL_INTEGRATION_SCOPES', 'crm,notifications')),
        ))),
        'redirect_uri' => env('AMO_EXTERNAL_REDIRECT_URI'),
        'secrets_uri' => env('AMO_EXTERNAL_SECRETS_URI'),
    ],
    'api_log_retention_days' => (int) env('API_LOG_RETENTION_DAYS', 30),
];
