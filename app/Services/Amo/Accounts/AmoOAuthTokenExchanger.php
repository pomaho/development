<?php

namespace App\Services\Amo\Accounts;

use AmoCRM\Client\AmoCRMApiClient;
use League\OAuth2\Client\Token\AccessTokenInterface;

class AmoOAuthTokenExchanger
{
    public function exchangeCode(
        string $baseDomain,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        string $code,
    ): AccessTokenInterface {
        $client = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);
        $client->setAccountBaseDomain($baseDomain);

        return $client->getOAuthClient()->getAccessTokenByCode($code);
    }
}
