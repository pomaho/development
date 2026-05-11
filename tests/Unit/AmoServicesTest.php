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
use App\Services\Amo\CrmAuditService;
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
                    && $payload[0]['_embedded']['statuses'][0]['color'] === '#98cbff'
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

    public function test_pipelines_service_collects_pipeline_details(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);

        $http->shouldReceive('get')->with($account, '/api/v4/leads/pipelines/10', Mockery::any())->andReturn([
            'id' => 10,
            'name' => 'Sales',
            'is_main' => true,
            'is_unsorted_on' => true,
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/leads/pipelines/10/statuses', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['statuses' => [
                ['id' => 20, 'name' => 'New', 'sort' => 10, 'color' => '#99ccff'],
                ['id' => 30, 'name' => 'Work', 'sort' => 20, 'color' => '#ffcc66'],
            ]],
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/leads/custom_fields', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['custom_fields' => [[
                'id' => 100,
                'name' => 'Project',
                'type' => 'select',
                'required_statuses' => [['pipeline_id' => 10, 'status_id' => 20]],
            ]]],
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/sources', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['sources' => [[
                'id' => 200,
                'name' => 'Website',
                'pipeline_id' => 10,
                'status_id' => 20,
            ]]],
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/widgets', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['widgets' => [
                ['code' => 'sonic', 'name' => 'Sonic Expert', 'is_installed' => true],
                ['code' => 'marketplace', 'name' => 'Marketplace Widget', 'is_installed' => false],
            ]],
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/website_buttons', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['website_buttons' => [['id' => 300, 'name' => 'Lead button', 'pipeline_id' => 10]]],
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/leads/loss_reasons', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['loss_reasons' => [['id' => 400, 'name' => 'No answer']]],
        ]);

        $details = (new AmoPipelinesService($http))->fetchPipelineDetails($account, 10);

        $this->assertSame('Sales', $details['pipeline']['name']);
        $this->assertSame('Project', $details['stage_rows'][0]['required_fields'][0]['name']);
        $this->assertSame('Website', $details['stage_rows'][0]['sources'][0]['name']);
        $this->assertSame('Sonic Expert', $details['widgets'][0]['name']);
        $this->assertCount(1, $details['widgets']);
        $this->assertSame('No answer', $details['loss_reasons'][0]['name']);
    }

    public function test_pipelines_service_clones_pipeline_with_statuses(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);

        $http->shouldReceive('get')->with($account, '/api/v4/leads/pipelines/10', Mockery::any())->andReturn([
            'id' => 10,
            'name' => 'Sales',
            'sort' => 20,
            'is_unsorted_on' => true,
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/leads/pipelines/10/statuses', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['statuses' => [
                ['id' => 19, 'name' => 'Неразобранное', 'sort' => 10, 'color' => '#c1c1c1', 'type' => 1],
                ['id' => 20, 'name' => 'New', 'sort' => 10, 'color' => '#99ccff', 'descriptions' => [
                    ['id' => 1, 'level' => 'newbie', 'description' => 'Подсказка новичку'],
                    ['id' => 2, 'level' => 'candidate', 'description' => 'Подсказка кандидату'],
                ]],
                ['id' => 142, 'name' => 'Успешно реализовано'],
                ['id' => 143, 'name' => 'Закрыто и не реализовано'],
            ]],
        ]);
        $http->shouldReceive('get')->with($account, '/api/v4/leads/custom_fields', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['custom_fields' => [[
                'id' => 100,
                'name' => 'Project',
                'required_statuses' => [['pipeline_id' => 10, 'status_id' => 20]],
            ]]],
        ]);
        foreach ([
            ['/api/v4/sources', 'sources'],
            ['/api/v4/widgets', 'widgets'],
            ['/api/v4/website_buttons', 'website_buttons'],
            ['/api/v4/leads/loss_reasons', 'loss_reasons'],
        ] as [$path, $key]) {
            $http->shouldReceive('get')->with($account, $path, Mockery::any())->andReturn([
                '_page' => 1,
                '_embedded' => [$key => []],
            ]);
        }
        $capturedPayload = null;
        $http->shouldReceive('post')
            ->once()
            ->with($account, '/api/v4/leads/pipelines', Mockery::on(function (array $payload) use (&$capturedPayload): bool {
                $capturedPayload = $payload;

                return true;
            }))
            ->andReturn(['_embedded' => ['pipelines' => [[
                'id' => 123,
                '_embedded' => ['statuses' => [
                    ['id' => 220, 'name' => 'New', 'sort' => 10, 'type' => 0],
                    ['id' => 142, 'name' => 'Успешно реализовано'],
                    ['id' => 143, 'name' => 'Закрыто и не реализовано'],
                ]],
            ]]]]);
        $http->shouldReceive('patch')
            ->once()
            ->with($account, '/api/v4/leads/custom_fields/100', Mockery::on(function (array $payload): bool {
                return $payload['name'] === 'Project'
                    && $payload['required_statuses'][0] === ['pipeline_id' => 10, 'status_id' => 20]
                    && $payload['required_statuses'][1] === ['pipeline_id' => 123, 'status_id' => 220];
            }))
            ->andReturn(['id' => 100]);

        $result = (new AmoPipelinesService($http))->clonePipeline($account, 10, 'Sales Copy');

        $this->assertSame('Sales Copy', $capturedPayload[0]['name']);
        $this->assertSame(30, $capturedPayload[0]['sort']);
        $this->assertFalse($capturedPayload[0]['is_main']);
        $this->assertTrue($capturedPayload[0]['is_unsorted_on']);
        $this->assertSame('New', $capturedPayload[0]['_embedded']['statuses'][0]['name']);
        $this->assertSame('#98cbff', $capturedPayload[0]['_embedded']['statuses'][0]['color']);
        $this->assertSame('newbie', $capturedPayload[0]['_embedded']['statuses'][0]['descriptions'][0]['level']);
        $this->assertSame('Подсказка новичку', $capturedPayload[0]['_embedded']['statuses'][0]['descriptions'][0]['description']);
        $this->assertSame(142, $capturedPayload[0]['_embedded']['statuses'][1]['id']);
        $this->assertSame(143, $capturedPayload[0]['_embedded']['statuses'][2]['id']);
        $this->assertCount(3, $capturedPayload[0]['_embedded']['statuses']);
        $this->assertSame(123, $result['_embedded']['pipelines'][0]['id']);
        $this->assertArrayNotHasKey('_clone_warnings', $result);
    }

    public function test_crm_audit_service_saves_structure_and_entities(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);

        $http->shouldReceive('get')->with($account, '/api/v4/leads/pipelines', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['pipelines' => [[
                'id' => 10,
                'name' => 'Sales',
                'sort' => 1,
                'is_main' => true,
                'is_unsorted_on' => true,
                '_embedded' => ['statuses' => [['id' => 20, 'name' => 'New', 'sort' => 10]]],
            ]]],
        ]);
        foreach (['leads', 'contacts', 'companies'] as $entityType) {
            $http->shouldReceive('get')->with($account, "/api/v4/{$entityType}/custom_fields", Mockery::any())->andReturn([
                '_page' => 1,
                '_embedded' => ['custom_fields' => [['id' => 100, 'name' => "{$entityType} field", 'type' => 'text']]],
            ]);
        }
        foreach ([
            ['/api/v4/leads/loss_reasons', 'loss_reasons'],
            ['/api/v4/sources', 'sources'],
            ['/api/v4/catalogs', 'catalogs'],
            ['/api/v4/leads', 'leads'],
            ['/api/v4/contacts', 'contacts'],
            ['/api/v4/companies', 'companies'],
            ['/api/v4/events', 'events'],
            ['/api/v4/tasks', 'tasks'],
            ['/api/v4/leads/unsorted', 'unsorted'],
        ] as [$path, $key]) {
            $http->shouldReceive('get')->with($account, $path, Mockery::any())->andReturn([
                '_page' => 1,
                '_embedded' => [$key => [['id' => 1, 'name' => $key]]],
            ]);
        }

        $counts = (new CrmAuditService($http))->syncAll($account);

        $this->assertSame(1, $counts['pipelines']);
        $this->assertDatabaseHas('crm_pipelines_snapshots', ['amo_pipeline_id' => 10, 'name' => 'Sales']);
        $this->assertDatabaseHas('crm_custom_fields_snapshots', ['entity_type' => 'leads', 'amo_field_id' => 100]);
        $this->assertDatabaseHas('crm_entity_snapshots', ['entity_type' => 'leads', 'external_id' => '1']);
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
