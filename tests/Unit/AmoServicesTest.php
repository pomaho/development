<?php

namespace Tests\Unit;

use App\Models\AmoAccount;
use App\Models\AmoCredential;
use App\Models\AmoRolesSnapshot;
use App\Models\AmoUsersSnapshot;
use App\Services\Amo\AmoClientFactory;
use App\Services\Amo\AmoFallbackHttpClient;
use App\Services\Amo\AmoPipelinesService;
use App\Services\Amo\AmoTokenManager;
use App\Services\Amo\AmoUsersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AmoServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_manager_returns_long_lived_token(): void
    {
        $token = $this->longLivedJwt();
        $account = $this->accountWithToken($token);

        $this->assertSame($token, app(AmoTokenManager::class)->accessTokenFor($account));
    }

    public function test_client_factory_targets_account_domain(): void
    {
        $account = $this->accountWithToken($this->longLivedJwt());

        $client = app(AmoClientFactory::class)->make($account);

        $this->assertTrue(method_exists($client, 'setAccountBaseDomain'));
    }

    public function test_users_service_handles_pagination_and_saves_snapshots(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);
        $http->shouldReceive('get')->with($account, '/api/v4/users', Mockery::on(fn ($query) => $query['page'] === 1))->andReturn([
            '_page' => 1,
            '_page_count' => 2,
            '_embedded' => ['users' => [['id' => 1, 'name' => 'Admin', 'rights' => ['is_admin' => true], 'is_active' => true]]],
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/users', Mockery::on(fn ($query) => $query['page'] === 2))->andReturn([
            '_page' => 2,
            '_page_count' => 2,
            '_embedded' => ['users' => [['id' => 2, 'name' => 'Viewer', 'rights' => [], 'is_active' => false]]],
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/roles', Mockery::any())->andReturn([
            '_page' => 1,
            '_page_count' => 1,
            '_embedded' => ['roles' => [['id' => 10, 'name' => 'Sales', 'rights' => [], '_embedded' => ['users' => [['id' => 1]]]]]],
        ]);

        (new AmoUsersService($http))->syncUsersAndRoles($account);

        $this->assertSame(2, AmoUsersSnapshot::query()->count());
        $this->assertSame(1, AmoRolesSnapshot::query()->count());
        $this->assertDatabaseHas('amo_users_snapshots', ['amo_user_id' => 1, 'is_admin' => true]);
    }

    public function test_pipelines_service_creates_pipeline_with_statuses(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->with($account, '/api/v4/leads/pipelines', Mockery::on(function (array $payload): bool {
                return $payload[0]['name'] === 'Продажи B2B'
                    && $payload[0]['is_unsorted_on'] === true
                    && $payload[0]['_embedded']['statuses'][0]['name'] === 'Первичный контакт'
                    && $payload[0]['_embedded']['statuses'][1]['id'] === 142;
            }))
            ->andReturn(['_embedded' => ['pipelines' => [['id' => 123]]]]);

        $result = (new AmoPipelinesService($http))->createPipeline($account, [
            'name' => 'Продажи B2B',
            'sort' => 20,
            'is_main' => false,
            'is_unsorted_on' => true,
            'statuses' => [
                ['name' => 'Первичный контакт', 'sort' => 10, 'color' => '#99ccff'],
                ['id' => 142, 'name' => 'Успешно реализовано'],
            ],
        ]);

        $this->assertSame(123, $result['_embedded']['pipelines'][0]['id']);
    }

    public function test_oauth_refresh_saves_new_refresh_token(): void
    {
        $this->markTestSkipped('Requires official amoCRM OAuth client network flow; covered by integration testing with real credentials.');
    }

    private function accountWithToken(string $token): AmoAccount
    {
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $account->credentials()->create(['auth_type' => AmoCredential::AUTH_LONG_LIVED, 'access_token' => $token]);

        return $account->refresh()->load('credentials');
    }

    private function longLivedJwt(): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['exp' => now()->addYear()->timestamp])), '+/', '-_'), '=');

        $signature = rtrim(strtr(base64_encode('signature'), '+/', '-_'), '=');

        return $header.'.'.$payload.'.'.$signature;
    }
}
