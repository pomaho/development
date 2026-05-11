<?php

namespace App\Services\Amo;

use App\Models\AmoAccount;
use App\Models\AmoCredential;
use App\Models\AmoOAuthConnection;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AmoExternalOAuthService
{
    public function __construct(
        private readonly AmoOAuthTokenExchanger $tokenExchanger,
        private readonly AmoAccountProfileService $accountProfileService,
    ) {
    }

    public function createPending(?User $owner = null, ?string $name = null): AmoOAuthConnection
    {
        return AmoOAuthConnection::query()->create([
            'owner_user_id' => $owner?->id,
            'state' => Str::random(64),
            'name' => $name ?: config('amo.external.name'),
            'redirect_uri' => config('amo.external.redirect_uri') ?: route('amo-oauth.callback'),
            'secrets_uri' => config('amo.external.secrets_uri') ?: route('amo-oauth.external.secrets'),
            'scopes' => config('amo.external.scopes'),
            'status' => AmoOAuthConnection::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function receiveSecrets(array $payload): AmoOAuthConnection
    {
        $connection = $this->findByState((string) ($payload['state'] ?? ''));

        $connection->forceFill([
            'client_id' => $payload['client_id'] ?? null,
            'client_secret' => $payload['client_secret'] ?? null,
            'status' => AmoOAuthConnection::STATUS_SECRETS_RECEIVED,
            'error_message' => null,
        ])->save();

        return $this->tryFinalize($connection->refresh());
    }

    public function receiveCallback(array $query): AmoOAuthConnection
    {
        $connection = $this->findByState((string) ($query['state'] ?? ''));

        if (! empty($query['error'])) {
            $connection->forceFill([
                'status' => AmoOAuthConnection::STATUS_FAILED,
                'error_message' => (string) $query['error'],
            ])->save();

            return $connection;
        }

        $connection->forceFill([
            'authorization_code' => $query['code'] ?? null,
            'base_domain' => $this->normalizeBaseDomain($query['referer'] ?? $query['base_domain'] ?? $query['account_domain'] ?? null),
            'status' => AmoOAuthConnection::STATUS_CODE_RECEIVED,
            'error_message' => null,
        ])->save();

        return $this->tryFinalize($connection->refresh());
    }

    public function tryFinalize(AmoOAuthConnection $connection): AmoOAuthConnection
    {
        if ($connection->status === AmoOAuthConnection::STATUS_CONNECTED) {
            return $connection;
        }

        if ($connection->expires_at?->isPast()) {
            $connection->forceFill([
                'status' => AmoOAuthConnection::STATUS_FAILED,
                'error_message' => 'OAuth connection state expired.',
            ])->save();

            return $connection;
        }

        if (! $connection->client_id || ! $connection->client_secret || ! $connection->authorization_code || ! $connection->base_domain) {
            return $connection;
        }

        try {
            $token = $this->tokenExchanger->exchangeCode(
                $connection->base_domain,
                (string) $connection->client_id,
                (string) $connection->client_secret,
                (string) $connection->redirect_uri,
                (string) $connection->authorization_code,
            );

            $account = AmoAccount::query()->updateOrCreate(
                ['base_domain' => $connection->base_domain],
                [
                    'owner_user_id' => $connection->owner_user_id,
                    'name' => $connection->name ?: $connection->base_domain,
                    'is_active' => true,
                    'auth_status' => 'ok',
                ],
            );

            $account->credentials()->updateOrCreate(
                ['amo_account_id' => $account->id],
                [
                    'auth_type' => AmoCredential::AUTH_OAUTH,
                    'access_token' => $token->getToken(),
                    'refresh_token' => $token->getRefreshToken(),
                    'client_id' => $connection->client_id,
                    'client_secret' => $connection->client_secret,
                    'redirect_uri' => $connection->redirect_uri,
                    'token_expires_at' => $token->getExpires() ? Carbon::createFromTimestamp($token->getExpires()) : null,
                    'scopes' => $connection->scopes,
                ],
            );

            $this->accountProfileService->refreshAfterInstall($account->refresh());
            $account->refresh();

            $connection->forceFill([
                'amo_account_id' => $account->id,
                'status' => AmoOAuthConnection::STATUS_CONNECTED,
                'error_message' => null,
                'connected_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $connection->forceFill([
                'status' => AmoOAuthConnection::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();
        }

        return $connection->refresh();
    }

    public function normalizeBaseDomain(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $host = parse_url(Str::startsWith($value, ['http://', 'https://']) ? $value : "https://{$value}", PHP_URL_HOST);

        if (! $host) {
            throw new RuntimeException('amoCRM account domain is missing.');
        }

        return Str::lower($host);
    }

    private function findByState(string $state): AmoOAuthConnection
    {
        if ($state === '') {
            throw new RuntimeException('OAuth state is missing.');
        }

        return AmoOAuthConnection::query()->where('state', $state)->firstOrFail();
    }
}
