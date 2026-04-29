<?php

namespace App\Services\Amo;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\LongLivedAccessToken;
use App\Models\AmoAccount;
use App\Models\AmoCredential;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Token\AccessToken;
use RuntimeException;
use Throwable;

class AmoTokenManager
{
    public function accessTokenFor(AmoAccount $account): string
    {
        $credential = $this->credentials($account);

        if ($credential->auth_type === AmoCredential::AUTH_LONG_LIVED) {
            return (string) $credential->access_token;
        }

        if ($this->shouldRefresh($credential->token_expires_at)) {
            $this->refreshOAuthToken($account);
            $credential->refresh();
        }

        return (string) $credential->access_token;
    }

    public function accessTokenObjectFor(AmoAccount $account): object
    {
        $credential = $this->credentials($account);
        $token = $this->accessTokenFor($account);

        if ($credential->auth_type === AmoCredential::AUTH_LONG_LIVED) {
            return new LongLivedAccessToken($token);
        }

        return new AccessToken([
            'access_token' => $token,
            'refresh_token' => $credential->refresh_token,
            'expires' => $credential->token_expires_at?->timestamp ?? now()->addHour()->timestamp,
        ]);
    }

    public function refreshOAuthToken(AmoAccount $account): AmoCredential
    {
        $credential = $this->credentials($account);

        if ($credential->auth_type !== AmoCredential::AUTH_OAUTH) {
            return $credential;
        }

        try {
            $client = new AmoCRMApiClient(
                (string) $credential->client_id,
                (string) $credential->client_secret,
                (string) $credential->redirect_uri,
            );
            $client->setAccountBaseDomain($account->base_domain);

            $newToken = $client->getOAuthClient()->getAccessTokenByRefreshToken(new AccessToken([
                'access_token' => (string) $credential->access_token,
                'refresh_token' => (string) $credential->refresh_token,
                'expires' => $credential->token_expires_at?->timestamp ?? now()->timestamp,
            ]));

            $credential->forceFill([
                'access_token' => $newToken->getToken(),
                'refresh_token' => $newToken->getRefreshToken() ?: $credential->refresh_token,
                'token_expires_at' => $newToken->getExpires() ? Carbon::createFromTimestamp($newToken->getExpires()) : null,
            ])->save();

            $account->forceFill(['auth_status' => 'ok'])->save();

            return $credential->refresh();
        } catch (Throwable $exception) {
            $account->forceFill(['auth_status' => 'reauth_required'])->save();
            Log::warning('amoCRM OAuth refresh failed', [
                'amo_account_id' => $account->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function shouldRefresh(?CarbonInterface $expiresAt): bool
    {
        return $expiresAt === null || $expiresAt->lessThanOrEqualTo(now()->addMinutes(10));
    }

    private function credentials(AmoAccount $account): AmoCredential
    {
        $credential = $account->credentials ?: $account->credentials()->first();

        if (! $credential || ! $credential->access_token) {
            throw new RuntimeException("amoCRM credentials are missing for account {$account->id}.");
        }

        return $credential;
    }
}
