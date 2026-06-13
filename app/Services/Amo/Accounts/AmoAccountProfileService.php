<?php

namespace App\Services\Amo\Accounts;

use App\Models\AmoAccount;
use App\Services\Amo\Client\AmoFallbackHttpClient;
use App\Services\Amo\Structure\AmoUsersService;
use Throwable;

class AmoAccountProfileService
{
    public function __construct(
        private readonly AmoFallbackHttpClient $http,
        private readonly AmoUsersService $usersService,
    ) {
    }

    public function refreshAfterInstall(AmoAccount $account): void
    {
        $this->refreshAccountSettings($account);

        try {
            $this->usersService->syncUsersAndRoles($account->refresh());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function refreshAccountSettings(AmoAccount $account): AmoAccount
    {
        $payload = $this->http->get($account, '/api/v4/account');

        $settings = [
            'company_name' => $payload['name'] ?? null,
            'subdomain' => $payload['subdomain'] ?? null,
            'timezone' => $payload['timezone'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'country' => $payload['country'] ?? null,
            'raw' => $payload,
        ];

        $account->forceFill([
            'account_id' => $payload['id'] ?? $account->account_id,
            'name' => ($payload['name'] ?? null) ?: $account->name,
            'settings' => $settings,
            'auth_status' => 'ok',
        ])->save();

        return $account->refresh();
    }
}
