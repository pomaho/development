<?php

namespace App\Services\Amo\Client;

use AmoCRM\Client\AmoCRMApiClient;
use App\Models\AmoAccount;

class AmoClientFactory
{
    public function __construct(private readonly AmoTokenManager $tokenManager)
    {
    }

    public function make(AmoAccount $account): AmoCRMApiClient
    {
        $credential = $account->credentials()->firstOrFail();

        $client = new AmoCRMApiClient(
            (string) $credential->client_id,
            (string) $credential->client_secret,
            (string) $credential->redirect_uri,
        );

        $client->setAccountBaseDomain($account->base_domain);
        $client->setAccessToken($this->tokenManager->accessTokenObjectFor($account));

        return $client;
    }
}
