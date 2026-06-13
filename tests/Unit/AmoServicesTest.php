<?php

namespace Tests\Unit;

use App\Jobs\SyncAmoTaskStatisticsJob;
use App\Models\AmoAccount;
use App\Models\AmoCredential;
use App\Models\AmoWebhookEvent;
use App\Models\CrmCustomFieldSnapshot;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Models\AmoRolesSnapshot;
use App\Models\AmoUsersSnapshot;
use App\Models\TaskStatisticsSyncRun;
use App\Services\Amo\Client\AmoClientFactory;
use App\Services\Amo\Structure\AmoCatalogsService;
use App\Services\Amo\Client\AmoFallbackHttpClient;
use App\Services\Amo\Automation\AmoLeadTransferService;
use App\Services\Amo\Structure\AmoPipelinesService;
use App\Services\Amo\Automation\AmoResponsibilityRedistributionService;
use App\Services\Amo\AmoTaskStatisticsService;
use App\Services\Amo\Client\AmoTokenManager;
use App\Services\Amo\Structure\AmoUsersService;
use App\Services\Amo\AmoWebhookService;
use App\Services\Amo\CrmAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            '_embedded' => ['users' => [[
                'id' => 1,
                'name' => 'Admin',
                'rights' => ['is_admin' => true],
                'is_active' => true,
                '_embedded' => [
                    'group' => ['id' => 30, 'name' => 'Авито рекрутинг'],
                    'role' => ['id' => 40, 'name' => 'Рекрутер'],
                ],
            ]]],
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
        $this->assertDatabaseHas('amo_users_snapshots', ['amo_user_id' => 1, 'group_id' => 30, 'role_id' => 40]);
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
                    && $payload[0]['_embedded']['statuses'][0]['descriptions'][0]['description'] === 'Проверить источник'
                    && $payload[0]['_embedded']['statuses'][1]['id'] === 142;
            }))
            ->andReturn(['_embedded' => ['pipelines' => [['id' => 123]]]]);

        $result = (new AmoPipelinesService($http))->createPipeline($account, [
            'name' => 'Продажи B2B',
            'sort' => 20,
            'is_main' => false,
            'is_unsorted_on' => true,
            'statuses' => [
                ['name' => 'Первичный контакт', 'sort' => 10, 'color' => '#99ccff', 'descriptions' => [['level' => 'newbie', 'description' => 'Проверить источник']]],
                ['id' => 142, 'name' => 'Успешно реализовано'],
            ],
        ]);

        $this->assertSame(123, $result['_embedded']['pipelines'][0]['id']);
    }

    public function test_catalogs_service_creates_catalog_elements_and_chained_list_field(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->with($account, '/api/v4/catalogs', Mockery::on(fn (array $payload): bool =>
                $payload[0]['name'] === 'Проекты'
                && $payload[0]['type'] === 'regular'
                && $payload[0]['can_show_in_cards'] === true
            ))
            ->andReturn(['_embedded' => ['catalogs' => [['id' => 1001]]]]);
        $http->shouldReceive('post')
            ->once()
            ->with($account, '/api/v4/catalogs/1001/elements', Mockery::on(fn (array $payload): bool =>
                $payload[0]['name'] === 'Проект А'
                && $payload[1]['name'] === 'Проект Б'
            ))
            ->andReturn(['_embedded' => ['elements' => [['id' => 2001]]]]);
        $http->shouldReceive('post')
            ->once()
            ->with($account, '/api/v4/leads/custom_fields', Mockery::on(fn (array $payload): bool =>
                $payload[0]['name'] === 'Проект / Вакансия'
                && $payload[0]['type'] === 'chained_list'
                && $payload[0]['chained_lists'][0]['catalog_id'] === 1001
                && $payload[0]['chained_lists'][0]['parent_catalog_id'] === 0
                && $payload[0]['chained_lists'][1]['catalog_id'] === 1002
                && $payload[0]['chained_lists'][1]['parent_catalog_id'] === 1001
            ))
            ->andReturn(['_embedded' => ['custom_fields' => [['id' => 3001]]]]);
        foreach (['leads', 'contacts', 'companies'] as $entity) {
            $http->shouldReceive('get')
                ->once()
                ->with($account, "/api/v4/{$entity}/custom_fields", Mockery::on(fn (array $query): bool => $query['page'] === 1 && $query['limit'] === 250))
                ->andReturn([
                    '_page' => 1,
                    '_page_count' => 1,
                    '_embedded' => [
                        'custom_fields' => $entity === 'leads' ? [
                            ['id' => 4001, 'name' => 'Источник', 'type' => 'select', 'enums' => [['id' => 10, 'value' => 'Авито']]],
                            ['id' => 4002, 'name' => 'Комментарий', 'type' => 'textarea', 'enums' => null],
                        ] : [],
                    ],
                ]);
        }
        $http->shouldReceive('patch')
            ->once()
            ->with($account, '/api/v4/leads/custom_fields/4001', Mockery::on(fn (array $payload): bool =>
                $payload['name'] === 'Источник'
                && $payload['enums'][0] === ['value' => 'Авито', 'sort' => 0, 'id' => 10]
                && $payload['enums'][1] === ['value' => 'Сайт', 'sort' => 1]
            ))
            ->andReturn(['id' => 4001]);

        $service = new AmoCatalogsService($http);
        $service->createCatalog($account, ['name' => 'Проекты', 'can_show_in_cards' => true]);
        $service->createElements($account, 1001, ['Проект А', '', 'Проект Б']);
        $service->createChainedListField($account, [
            'name' => 'Проект / Вакансия',
            'entity_type' => 'leads',
            'levels' => [
                ['title' => 'Проект', 'catalog_id' => 1001, 'parent_catalog_id' => 0],
                ['title' => 'Вакансия', 'catalog_id' => 1002, 'parent_catalog_id' => 1001],
            ],
        ]);

        $fields = $service->fetchEnumCustomFields($account);
        $this->assertSame('Источник', $fields[0]['name']);
        $this->assertSame('leads', $fields[0]['entity_type']);

        $service->updateEnumCustomField($account, 'leads', 4001, [
            'name' => 'Источник',
            'enums' => [
                ['id' => 10, 'value' => 'Авито'],
                ['value' => 'Сайт'],
            ],
        ]);
    }

    public function test_lead_transfer_service_maps_statuses_and_updates_snapshots(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        CrmPipelineStatusSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'amo_status_id' => 101,
            'name' => 'Первичный контакт',
            'sort' => 10,
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmPipelineStatusSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 20,
            'amo_status_id' => 201,
            'name' => 'Первичный контакт',
            'sort' => 10,
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmEntitySnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'external_id' => '1001',
            'name' => 'Lead A',
            'pipeline_id' => 10,
            'status_id' => 101,
            'embedded' => [],
            'raw' => [],
            'synced_at' => now(),
        ]);

        $http = Mockery::mock(AmoFallbackHttpClient::class);
        $http->shouldReceive('patch')
            ->once()
            ->with($account, '/api/v4/leads', Mockery::on(fn (array $payload): bool =>
                $payload[0] === ['id' => 1001, 'pipeline_id' => 20, 'status_id' => 201]
            ))
            ->andReturn(['_embedded' => ['leads' => [['id' => 1001]]]]);

        $service = new AmoLeadTransferService($http);
        $plan = $service->plan($account, 10, 20);

        $this->assertSame(1, $plan['transferable_leads']);

        $result = $service->transfer($account, 10, 20, [101 => 201]);

        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('crm_entity_snapshots', [
            'external_id' => '1001',
            'pipeline_id' => 20,
            'status_id' => 201,
        ]);
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

    public function test_responsibility_redistribution_previews_round_robin_contacts_and_linked_leads(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);

        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/contacts', Mockery::on(fn (array $query): bool =>
                $query['filter[responsible_user_id]'] === 10
                && $query['with'] === 'leads'
                && $query['page'] === 1
            ))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['contacts' => [
                    ['id' => 100, 'name' => 'Contact A', '_embedded' => ['leads' => [['id' => 501], ['id' => 502]]]],
                    ['id' => 101, 'name' => 'Contact B', '_embedded' => ['leads' => [['id' => 503]]]],
                    ['id' => 102, 'name' => 'Contact C', '_embedded' => ['leads' => [['id' => 504]]]],
                ]],
            ]);

        $preview = (new AmoResponsibilityRedistributionService($http))->preview($account, 10, [20, 30]);

        $this->assertSame(3, $preview['contacts_count']);
        $this->assertSame(4, $preview['leads_count']);
        $this->assertSame([
            ['target_user_id' => 20, 'contacts_count' => 2, 'leads_count' => 3, 'tasks_count' => 0],
            ['target_user_id' => 30, 'contacts_count' => 1, 'leads_count' => 1, 'tasks_count' => 0],
        ], $preview['by_target']);
    }

    public function test_responsibility_redistribution_uses_next_link_when_page_count_is_missing(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);

        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/contacts', Mockery::on(fn (array $query): bool =>
                $query['filter[responsible_user_id]'] === 10
                && $query['page'] === 1
            ))
            ->andReturn([
                '_page' => 1,
                '_links' => ['next' => ['href' => 'https://client.amocrm.ru/api/v4/contacts?page=2']],
                '_embedded' => ['contacts' => [
                    ['id' => 100, 'name' => 'Contact A', '_embedded' => ['leads' => []]],
                ]],
            ]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/contacts', Mockery::on(fn (array $query): bool =>
                $query['filter[responsible_user_id]'] === 10
                && $query['page'] === 2
            ))
            ->andReturn([
                '_page' => 2,
                '_embedded' => ['contacts' => [
                    ['id' => 101, 'name' => 'Contact B', '_embedded' => ['leads' => []]],
                ]],
            ]);

        $preview = (new AmoResponsibilityRedistributionService($http))->preview($account, 10, [20]);

        $this->assertSame(2, $preview['contacts_count']);
        $this->assertSame(2, $preview['by_target'][0]['contacts_count']);
    }

    public function test_responsibility_redistribution_can_update_linked_tasks_when_enabled(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);

        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/contacts', Mockery::on(fn (array $query): bool =>
                $query['filter[responsible_user_id]'] === 10
                && $query['with'] === 'leads'
                && $query['page'] === 1
            ))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['contacts' => [
                    ['id' => 100, 'name' => 'Contact A', '_embedded' => ['leads' => [['id' => 501]]]],
                ]],
            ]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/tasks', Mockery::on(fn (array $query): bool =>
                $query['filter[entity_type]'] === 'contacts'
                && $query['filter[entity_id]'] === [100]
                && $query['page'] === 1
            ))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['tasks' => [['id' => 900, 'entity_id' => 100]]],
            ]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/tasks', Mockery::on(fn (array $query): bool =>
                $query['filter[entity_type]'] === 'leads'
                && $query['filter[entity_id]'] === [501]
                && $query['page'] === 1
            ))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['tasks' => [['id' => 901, 'entity_id' => 501]]],
            ]);
        $http->shouldReceive('patch')->once()->with($account, '/api/v4/contacts', Mockery::any())->andReturn([]);
        $http->shouldReceive('patch')->once()->with($account, '/api/v4/leads', Mockery::any())->andReturn([]);
        $http->shouldReceive('patch')
            ->once()
            ->with($account, '/api/v4/tasks', Mockery::on(fn (array $payload): bool =>
                $payload === [
                    ['id' => 900, 'responsible_user_id' => 20],
                    ['id' => 901, 'responsible_user_id' => 20],
                ]
            ))
            ->andReturn([]);
        $http->shouldReceive('get')->once()->with($account, '/api/v4/contacts', Mockery::any())->andReturn(['_page' => 1, '_page_count' => 1, '_embedded' => ['contacts' => []]]);
        $http->shouldReceive('get')->once()->with($account, '/api/v4/leads', Mockery::any())->andReturn(['_page' => 1, '_page_count' => 1, '_embedded' => ['leads' => []]]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/tasks', Mockery::on(fn (array $query): bool =>
                $query['filter[responsible_user_id]'] === 10
                && $query['page'] === 1
            ))
            ->andReturn(['_page' => 1, '_page_count' => 1, '_embedded' => ['tasks' => []]]);

        $result = (new AmoResponsibilityRedistributionService($http))->redistribute($account, 10, [20], true);

        $this->assertSame(2, $result['updated_tasks']);
        $this->assertSame(0, $result['remaining_tasks_count']);
        $this->assertSame(2, $result['by_target'][0]['tasks_count']);
    }

    public function test_responsibility_redistribution_updates_contacts_and_linked_leads_with_same_manager(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);

        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/contacts', Mockery::on(fn (array $query): bool =>
                $query['filter[responsible_user_id]'] === 10
                && $query['with'] === 'leads'
                && $query['page'] === 1
            ))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['contacts' => [
                    ['id' => 100, 'name' => 'Contact A', '_embedded' => ['leads' => [['id' => 501], ['id' => 502]]]],
                    ['id' => 101, 'name' => 'Contact B', '_embedded' => ['leads' => [['id' => 503]]]],
                ]],
            ]);
        $http->shouldReceive('patch')
            ->once()
            ->with($account, '/api/v4/contacts', Mockery::on(fn (array $payload): bool =>
                $payload === [
                    ['id' => 100, 'responsible_user_id' => 20],
                    ['id' => 101, 'responsible_user_id' => 30],
                ]
            ))
            ->andReturn([]);
        $http->shouldReceive('patch')
            ->once()
            ->with($account, '/api/v4/leads', Mockery::on(fn (array $payload): bool =>
                $payload === [
                    ['id' => 501, 'responsible_user_id' => 20],
                    ['id' => 502, 'responsible_user_id' => 20],
                    ['id' => 503, 'responsible_user_id' => 30],
                ]
            ))
            ->andReturn([]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/contacts', Mockery::on(fn (array $query): bool =>
                $query['filter[responsible_user_id]'] === 10
                && $query['with'] === 'leads'
                && $query['page'] === 1
            ))
            ->andReturn(['_page' => 1, '_page_count' => 1, '_embedded' => ['contacts' => []]]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/leads', Mockery::on(fn (array $query): bool =>
                $query['filter[responsible_user_id]'] === 10
                && $query['page'] === 1
            ))
            ->andReturn(['_page' => 1, '_page_count' => 1, '_embedded' => ['leads' => []]]);

        $result = (new AmoResponsibilityRedistributionService($http))->redistribute($account, 10, [20, 30]);

        $this->assertSame(2, $result['updated_contacts']);
        $this->assertSame(3, $result['updated_leads']);
        $this->assertSame(0, $result['remaining_contacts_count']);
        $this->assertSame(0, $result['remaining_leads_count']);
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

    public function test_task_statistics_service_syncs_tasks_and_counts_completed_and_overdue(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        AmoUsersSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_user_id' => 10,
            'name' => 'Manager',
            'rights' => [],
            'group_id' => 20,
            'is_admin' => false,
            'is_active' => true,
            'raw' => [],
            'synced_at' => now(),
        ]);

        $from = now()->subWeek()->startOfDay();
        $to = now()->endOfDay();
        $http = Mockery::mock(AmoFallbackHttpClient::class);
        $longTaskText = str_repeat('https://example.test/tender?message=abcdef ', 20).'Финальный этап по тендеру';
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/tasks', Mockery::on(fn (array $query): bool =>
                $query['filter[is_completed]'] === 1
                && $query['filter[updated_at][from]'] === $from->timestamp
                && $query['filter[updated_at][to]'] === $to->timestamp
            ))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['tasks' => [[
                    'id' => 100,
                    'responsible_user_id' => 10,
                    'is_completed' => true,
                    'text' => $longTaskText,
                    'created_at' => now()->subDays(2)->timestamp,
                    'updated_at' => now()->subDay()->timestamp,
                    'complete_till' => now()->subDays(2)->timestamp,
                ]]],
            ]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/events', Mockery::on(fn (array $query): bool =>
                $query['filter[type][]'] === 'task_completed'
                && $query['filter[entity][]'] === 'task'
                && $query['filter[created_at][from]'] === $from->timestamp
                && $query['filter[created_at][to]'] === $to->timestamp
            ))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['events' => [[
                    'id' => 'event-100',
                    'entity_id' => 100,
                    'entity_type' => 'task',
                    'type' => 'task_completed',
                    'created_by' => 10,
                    'created_at' => now()->subDay()->timestamp,
                ]]],
            ]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/tasks', Mockery::on(fn (array $query): bool =>
                $query['filter[id]'] === [100]
                && $query['page'] === 1
            ))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['tasks' => [[
                    'id' => 100,
                    'responsible_user_id' => 10,
                    'is_completed' => true,
                    'text' => $longTaskText,
                    'created_at' => now()->subDays(2)->timestamp,
                    'updated_at' => now()->timestamp,
                    'complete_till' => now()->subDays(2)->timestamp,
                ]]],
            ]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/tasks', Mockery::on(fn (array $query): bool => $query['filter[is_completed]'] === 0))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['tasks' => [
                    [
                        'id' => 101,
                        'responsible_user_id' => 10,
                        'is_completed' => false,
                        'text' => 'Overdue',
                        'created_at' => now()->subDays(2)->timestamp,
                        'updated_at' => now()->subDay()->timestamp,
                        'complete_till' => now()->subHour()->timestamp,
                    ],
                    [
                        'id' => 102,
                        'responsible_user_id' => 10,
                        'is_completed' => false,
                        'text' => 'Future',
                        'created_at' => now()->subDays(2)->timestamp,
                        'updated_at' => now()->subDay()->timestamp,
                        'complete_till' => now()->addDay()->timestamp,
                    ],
                ]],
            ]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/events', Mockery::on(fn (array $query): bool =>
                ! isset($query['filter[entity][]'])
                && $query['filter[created_at][from]'] === $from->timestamp
                && $query['filter[created_at][to]'] === $to->timestamp
            ))
            ->andReturn([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => ['events' => [[
                    'id' => 'lead-event-100',
                    'entity_id' => 500,
                    'entity_type' => 'lead',
                    'type' => 'lead_status_changed',
                    'created_by' => 10,
                    'created_at' => now()->subDay()->timestamp,
                ]]],
            ]);

        $run = TaskStatisticsSyncRun::query()->create([
            'amo_account_id' => $account->id,
            'period_from' => $from,
            'period_to' => $to,
        ]);
        $service = new AmoTaskStatisticsService($http);
        $syncCounts = $service->sync($account, $from, $to, $run);
        $run->refresh();
        $rows = $service->statistics($account, $from, $to);

        $this->assertSame(['completed' => 1, 'completion_events' => 1, 'open' => 2, 'events' => 1], $syncCounts);
        $this->assertSame(TaskStatisticsSyncRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(1, $run->completed_found);
        $this->assertSame(1, $run->completed_synced);
        $this->assertSame(1, $run->completion_events_found);
        $this->assertSame(1, $run->completion_events_synced);
        $this->assertSame(2, $run->open_found);
        $this->assertSame(2, $run->open_synced);
        $task = CrmEntitySnapshot::query()->where('entity_type', 'tasks')->where('external_id', '100')->firstOrFail();
        $this->assertLessThanOrEqual(250, mb_strlen((string) $task->name));
        $this->assertSame($longTaskText, $task->raw['text']);
        $this->assertSame('event-100', $task->raw['_task_statistics']['completed_event_id']);
        $this->assertSame('Manager', $rows[0]['responsible_name']);
        $this->assertSame(1, $rows[0]['completed_count']);
        $this->assertSame(1, $rows[0]['completed_overdue_count']);
        $this->assertSame(2, $rows[0]['open_count']);
        $this->assertSame(1, $rows[0]['open_overdue_count']);
        $this->assertSame(2, $rows[0]['overdue_count']);
        $this->assertSame(66.7, $rows[0]['overdue_rate']);

        $groups = $service->completedOverdueDashboard($account, $from, $to);

        $this->assertSame('Группа 20', $groups[0]['group_name']);
        $this->assertSame(1, $groups[0]['completed_count']);
        $this->assertSame(1, $groups[0]['completed_overdue_count']);
        $this->assertSame('Manager', $groups[0]['users'][0]['name']);
        $this->assertSame(100.0, $groups[0]['users'][0]['overdue_rate']);
    }

    public function test_task_dashboard_cache_refreshes_by_version(): void
    {
        Cache::flush();

        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        AmoUsersSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_user_id' => 10,
            'name' => 'Manager',
            'rights' => [],
            'group_id' => 20,
            'is_admin' => false,
            'is_active' => true,
            'raw' => ['_embedded' => ['group' => ['name' => 'Sales']]],
            'synced_at' => now(),
        ]);

        $service = new AmoTaskStatisticsService(Mockery::mock(AmoFallbackHttpClient::class));
        $from = now()->subDays(3)->startOfDay();
        $to = now()->endOfDay();

        $this->completedTaskSnapshot($account, '100', 10);
        $first = $service->completedOverdueDashboard($account, $from, $to);
        $this->assertSame(1, $first[0]['completed_count']);

        $this->completedTaskSnapshot($account, '101', 10);
        $cached = $service->completedOverdueDashboard($account, $from, $to);
        $this->assertSame(1, $cached[0]['completed_count']);

        $service->refreshDashboardCacheVersion($account);
        $fresh = $service->completedOverdueDashboard($account, $from, $to);
        $this->assertSame(2, $fresh[0]['completed_count']);
    }

    public function test_recruiter_lead_distribution_counts_leads_by_recruiter_field_enum(): void
    {
        Cache::flush();

        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        CrmCustomFieldSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'amo_field_id' => 777,
            'name' => 'Рекрутер',
            'field_type' => 'select',
            'enums' => [
                ['id' => 1001, 'value' => 'Иван Рекрутер'],
                ['id' => 1002, 'value' => 'Мария Рекрутер'],
                ['id' => 1003, 'value' => 'Без сделок'],
            ],
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmCustomFieldSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'amo_field_id' => 778,
            'name' => 'Менеджер',
            'field_type' => 'select',
            'enums' => [
                ['id' => 2001, 'value' => 'Первый менеджер'],
                ['id' => 2002, 'value' => 'Второй менеджер'],
            ],
            'raw' => [],
            'synced_at' => now(),
        ]);

        foreach ([
            ['id' => '501', 'enum_id' => 1001, 'value' => 'Иван Рекрутер', 'status_id' => 111, 'pipeline_id' => 10, 'created_at' => now()->subDay(), 'manager' => 'Первый менеджер'],
            ['id' => '502', 'enum_id' => 1001, 'value' => 'Иван Рекрутер', 'status_id' => 142, 'pipeline_id' => 10, 'created_at' => now()->subDay(), 'manager' => null],
            ['id' => '503', 'enum_id' => 1002, 'value' => 'Мария Рекрутер', 'status_id' => 143, 'pipeline_id' => 10, 'created_at' => now()->subDay(), 'manager' => 'Второй менеджер'],
            ['id' => '504', 'enum_id' => 1002, 'value' => 'Мария Рекрутер', 'status_id' => 111, 'pipeline_id' => 20, 'created_at' => now()->subDay(), 'manager' => 'Второй менеджер'],
            ['id' => '505', 'enum_id' => null, 'value' => 'Мария Рекрутер', 'status_id' => 111, 'pipeline_id' => 10, 'created_at' => now()->subDay(), 'manager' => 'Второй менеджер'],
            ['id' => '506', 'enum_id' => 1002, 'value' => 'Мария Рекрутер', 'status_id' => 111, 'pipeline_id' => 10, 'created_at' => now()->subYear(), 'manager' => 'Второй менеджер'],
        ] as $lead) {
            CrmEntitySnapshot::query()->create([
                'amo_account_id' => $account->id,
                'entity_type' => 'leads',
                'external_id' => $lead['id'],
                'name' => 'Lead '.$lead['id'],
                'pipeline_id' => $lead['pipeline_id'],
                'status_id' => $lead['status_id'],
                'entity_created_at' => $lead['created_at'],
                'custom_fields_values' => [[
                    'field_id' => 777,
                    'field_name' => 'Рекрутер',
                    'values' => [array_filter([
                        'enum_id' => $lead['enum_id'],
                        'value' => $lead['value'],
                    ], fn ($value): bool => $value !== null)],
                ], [
                    'field_id' => 778,
                    'field_name' => 'Менеджер',
                    'values' => $lead['manager'] === null ? [] : [[
                        'value' => $lead['manager'],
                    ]],
                ]],
                'raw' => [],
                'synced_at' => now(),
            ]);
        }

        $distribution = (new AmoTaskStatisticsService(Mockery::mock(AmoFallbackHttpClient::class)))
            ->recruiterLeadDistribution($account, now()->subDays(7), now(), [
                'pipeline_id' => 10,
                'pipeline_name' => 'Массовый подбор',
                'recruiter_field_id' => 777,
                'recruiter_field_name' => 'Рекрутер',
                'manager_field_id' => 778,
                'manager_field_name' => 'Менеджер',
            ]);

        $this->assertTrue($distribution['field_found']);
        $this->assertTrue($distribution['manager_field_found']);
        $this->assertSame(10, $distribution['pipeline_id']);
        $this->assertSame('Массовый подбор', $distribution['pipeline_name']);
        $this->assertSame(4, $distribution['total_leads_count']);
        $this->assertSame(4, $distribution['assigned_leads_count']);
        $this->assertSame(3, $distribution['transferred_to_manager_count']);
        $this->assertSame('Иван Рекрутер', $distribution['recruiters'][0]['name']);
        $this->assertSame(2, $distribution['recruiters'][0]['leads_count']);
        $this->assertSame(1, $distribution['recruiters'][0]['transferred_to_manager_count']);
        $this->assertSame('Мария Рекрутер', $distribution['recruiters'][1]['name']);
        $this->assertSame(2, $distribution['recruiters'][1]['leads_count']);
        $this->assertSame(2, $distribution['recruiters'][1]['transferred_to_manager_count']);
        $this->assertSame('Без сделок', $distribution['recruiters'][2]['name']);
        $this->assertSame(0, $distribution['recruiters'][2]['leads_count']);
        $this->assertSame(0, $distribution['recruiters'][2]['transferred_to_manager_count']);
    }

    public function test_recruiter_lead_distribution_diagnostics_explains_local_data_match(): void
    {
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        CrmCustomFieldSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'amo_field_id' => 777,
            'name' => 'Рекрутер',
            'field_type' => 'select',
            'enums' => [
                ['id' => 1001, 'value' => 'Иван Рекрутер'],
                ['id' => 1002, 'value' => 'Мария Рекрутер'],
            ],
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmEntitySnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'external_id' => '501',
            'name' => 'Lead 501',
            'pipeline_id' => 10,
            'status_id' => 111,
            'entity_created_at' => now()->subDay(),
            'custom_fields_values' => [[
                'field_id' => 777,
                'field_name' => 'Рекрутер',
                'values' => [['value' => 'Иван Рекрутер']],
            ]],
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmEntitySnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'external_id' => '502',
            'name' => 'Lead 502',
            'pipeline_id' => 20,
            'status_id' => 222,
            'entity_created_at' => now()->subDay(),
            'custom_fields_values' => [],
            'raw' => [],
            'synced_at' => now(),
        ]);

        $diagnostics = (new AmoTaskStatisticsService(Mockery::mock(AmoFallbackHttpClient::class)))
            ->recruiterLeadDistributionDiagnostics($account, now()->subDays(7), now(), [
                'pipeline_id' => 10,
                'pipeline_name' => 'Массовый подбор',
                'recruiter_field_id' => 777,
                'recruiter_field_name' => 'Рекрутер',
            ]);

        $this->assertTrue($diagnostics['field_found']);
        $this->assertSame(2, $diagnostics['synced_leads_total']);
        $this->assertSame(1, $diagnostics['pipeline_leads_total']);
        $this->assertSame(1, $diagnostics['pipeline_period_leads_total']);
        $this->assertSame(1, $diagnostics['leads_with_field']);
        $this->assertSame(1, $diagnostics['assigned_leads']);
        $this->assertSame(1001, $diagnostics['field_values'][0]['enum_id']);
        $this->assertSame('Иван Рекрутер', $diagnostics['field_values'][0]['value']);
        $this->assertSame('501', $diagnostics['sample_leads'][0]['id']);
    }

    public function test_recruiter_team_city_breakdown_groups_manager_handoffs(): void
    {
        Cache::flush();

        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        foreach ([
            [777, 'Рекрутер', [['id' => 1001, 'value' => 'Косыева Лилия'], ['id' => 1002, 'value' => 'Иван Рекрутер']]],
            [778, 'Менеджер', [['id' => 2001, 'value' => 'Первый менеджер']]],
            [779, 'Команда', [['id' => 3001, 'value' => 'Альфа'], ['id' => 3002, 'value' => 'Бетта']]],
            [780, 'Город', [['id' => 4001, 'value' => 'Москва'], ['id' => 4002, 'value' => 'Омск'], ['id' => 4003, 'value' => 'Санкт-Петербург']]],
            [781, 'Источник', [['id' => 5001, 'value' => 'Авито'], ['id' => 5002, 'value' => 'Сайт']]],
        ] as [$fieldId, $name, $enums]) {
            CrmCustomFieldSnapshot::query()->create([
                'amo_account_id' => $account->id,
                'entity_type' => 'leads',
                'amo_field_id' => $fieldId,
                'name' => $name,
                'field_type' => 'select',
                'enums' => $enums,
                'raw' => [],
                'synced_at' => now(),
            ]);
        }

        foreach ([
            ['id' => '501', 'recruiter' => 'Косыева Лилия', 'manager' => 'Первый менеджер', 'team' => 'Альфа', 'city' => 'Москва', 'source' => 'Авито'],
            ['id' => '502', 'recruiter' => 'Косыева Лилия', 'manager' => 'Первый менеджер', 'team' => 'Альфа', 'city' => 'Москва', 'source' => 'Сайт'],
            ['id' => '503', 'recruiter' => 'Косыева Лилия', 'manager' => 'Первый менеджер', 'team' => 'Альфа', 'city' => 'Омск', 'source' => 'Авито'],
            ['id' => '504', 'recruiter' => 'Косыева Лилия', 'manager' => 'Первый менеджер', 'team' => 'Бетта', 'city' => 'Санкт-Петербург', 'source' => 'Сайт'],
            ['id' => '505', 'recruiter' => 'Иван Рекрутер', 'manager' => 'Первый менеджер', 'team' => 'Альфа', 'city' => 'Омск', 'source' => 'Авито'],
            ['id' => '506', 'recruiter' => 'Косыева Лилия', 'manager' => null, 'team' => 'Альфа', 'city' => 'Москва', 'source' => 'Авито'],
        ] as $lead) {
            CrmEntitySnapshot::query()->create([
                'amo_account_id' => $account->id,
                'entity_type' => 'leads',
                'external_id' => $lead['id'],
                'name' => 'Lead '.$lead['id'],
                'pipeline_id' => 10,
                'status_id' => 111,
                'entity_created_at' => now()->subDay(),
                'custom_fields_values' => [
                    ['field_id' => 777, 'field_name' => 'Рекрутер', 'values' => [['value' => $lead['recruiter']]]],
                    ['field_id' => 778, 'field_name' => 'Менеджер', 'values' => $lead['manager'] === null ? [] : [['value' => $lead['manager']]]],
                    ['field_id' => 779, 'field_name' => 'Команда', 'values' => [['value' => $lead['team']]]],
                    ['field_id' => 780, 'field_name' => 'Город', 'values' => [['value' => $lead['city']]]],
                    ['field_id' => 781, 'field_name' => 'Источник', 'values' => [['value' => $lead['source']]]],
                ],
                'raw' => [],
                'synced_at' => now(),
            ]);
        }

        $breakdown = (new AmoTaskStatisticsService(Mockery::mock(AmoFallbackHttpClient::class)))
            ->recruiterTeamCityBreakdown($account, now()->subDays(7), now(), [
                'pipeline_id' => 10,
                'recruiter_field_id' => 777,
                'manager_field_id' => 778,
                'team_field_id' => 779,
                'city_field_id' => 780,
                'source_field_id' => 781,
            ]);

        $this->assertSame(5, $breakdown['total_leads_count']);
        $this->assertSame(['Авито', 'Сайт'], $breakdown['source_columns']);
        $this->assertTrue($breakdown['source_field_found']);
        $this->assertSame('Косыева Лилия', $breakdown['recruiters'][0]['name']);
        $this->assertSame(4, $breakdown['recruiters'][0]['total_leads_count']);
        $this->assertSame('Альфа', $breakdown['recruiters'][0]['teams'][0]['name']);
        $this->assertSame(3, $breakdown['recruiters'][0]['teams'][0]['total_leads_count']);
        $this->assertSame('Москва', $breakdown['recruiters'][0]['teams'][0]['cities'][0]['name']);
        $this->assertSame(2, $breakdown['recruiters'][0]['teams'][0]['cities'][0]['leads_count']);
        $this->assertSame(1, $breakdown['recruiters'][0]['teams'][0]['cities'][0]['sources']['Авито']);
        $this->assertSame(1, $breakdown['recruiters'][0]['teams'][0]['cities'][0]['sources']['Сайт']);
        $this->assertSame('Омск', $breakdown['recruiters'][0]['teams'][0]['cities'][1]['name']);
        $this->assertSame(1, $breakdown['recruiters'][0]['teams'][0]['cities'][1]['leads_count']);
        $this->assertSame(1, $breakdown['recruiters'][0]['teams'][0]['cities'][1]['sources']['Авито']);
        $this->assertSame(0, $breakdown['recruiters'][0]['teams'][0]['cities'][1]['sources']['Сайт']);
        $this->assertSame('Иван Рекрутер', $breakdown['recruiters'][1]['name']);
        $this->assertSame(1, $breakdown['recruiters'][1]['total_leads_count']);
    }

    public function test_task_statistics_job_updates_incremental_cursor(): void
    {
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $run = TaskStatisticsSyncRun::query()->create([
            'amo_account_id' => $account->id,
            'period_from' => now()->subHour(),
            'period_to' => now(),
        ]);
        $service = Mockery::mock(AmoTaskStatisticsService::class);
        $service->shouldReceive('sync')
            ->once()
            ->with(Mockery::type(AmoAccount::class), Mockery::any(), Mockery::any(), Mockery::type(TaskStatisticsSyncRun::class))
            ->andReturn(['completed' => 0, 'completion_events' => 0, 'open' => 0]);

        (new SyncAmoTaskStatisticsJob($run->id))->handle($service);

        $this->assertSame($run->period_to->toIso8601String(), $account->refresh()->taskStatisticsLastSuccessfulSyncAt()?->toIso8601String());
    }

    public function test_crm_audit_service_can_sync_selected_pipeline(): void
    {
        $account = $this->accountWithToken('abcdef123456');
        $http = Mockery::mock(AmoFallbackHttpClient::class);

        $http->shouldReceive('get')->with($account, '/api/v4/leads/pipelines', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['pipelines' => [
                [
                    'id' => 10,
                    'name' => 'Target',
                    'sort' => 1,
                    '_embedded' => ['statuses' => [['id' => 20, 'name' => 'New', 'sort' => 10]]],
                ],
                [
                    'id' => 99,
                    'name' => 'Other',
                    'sort' => 2,
                    '_embedded' => ['statuses' => [['id' => 88, 'name' => 'Other stage', 'sort' => 10]]],
                ],
            ]],
        ]);
        foreach (['leads', 'contacts', 'companies'] as $entityType) {
            $http->shouldReceive('get')->with($account, "/api/v4/{$entityType}/custom_fields", Mockery::any())->andReturn([
                '_page' => 1,
                '_embedded' => ['custom_fields' => []],
            ]);
        }
        foreach ([
            ['/api/v4/leads/loss_reasons', 'loss_reasons'],
            ['/api/v4/sources', 'sources'],
            ['/api/v4/catalogs', 'catalogs'],
        ] as [$path, $key]) {
            $http->shouldReceive('get')->with($account, $path, Mockery::any())->andReturn([
                '_page' => 1,
                '_embedded' => [$key => []],
            ]);
        }
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/leads', Mockery::on(fn (array $query): bool => (int) $query['filter[pipeline_id]'] === 10))
            ->andReturn([
                '_page' => 1,
                '_embedded' => ['leads' => []],
            ]);
        $http->shouldReceive('get')
            ->once()
            ->with($account, '/api/v4/leads', Mockery::on(fn (array $query): bool => ! isset($query['filter[pipeline_id]'])))
            ->andReturn([
                '_page' => 1,
                '_embedded' => ['leads' => [
                    ['id' => 100, 'name' => 'Lead', 'pipeline_id' => 10, 'status_id' => 20],
                    ['id' => 200, 'name' => 'Other Lead', 'pipeline_id' => 99, 'status_id' => 88],
                ]],
            ]);

        $counts = (new CrmAuditService($http))->syncAll($account, null, null, 10);

        $this->assertSame(1, $counts['pipelines']);
        $this->assertSame(1, $counts['leads']);
        $this->assertDatabaseHas('crm_pipelines_snapshots', ['amo_pipeline_id' => 10, 'name' => 'Target']);
        $this->assertDatabaseMissing('crm_pipelines_snapshots', ['amo_pipeline_id' => 99]);
        $this->assertDatabaseHas('crm_entity_snapshots', ['entity_type' => 'leads', 'external_id' => '100', 'pipeline_id' => 10]);
        $this->assertDatabaseMissing('crm_entity_snapshots', ['entity_type' => 'leads', 'external_id' => '200']);
    }

    public function test_amo_webhook_service_refreshes_lead_snapshot(): void
    {
        $account = $this->accountWithToken($this->longLivedJwt());
        $event = AmoWebhookEvent::query()->create([
            'amo_account_id' => $account->id,
            'event_type' => 'leads.update',
            'entity_type' => 'leads',
            'entity_id' => '100',
            'payload' => ['id' => 100],
            'status' => AmoWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);
        $http = Mockery::mock(AmoFallbackHttpClient::class);
        $http->shouldReceive('get')
            ->once()
            ->with(
                Mockery::on(fn (AmoAccount $passedAccount): bool => $passedAccount->id === $account->id),
                '/api/v4/leads/100',
                ['with' => 'contacts,loss_reason,source']
            )
            ->andReturn([
                'id' => 100,
                'name' => 'Updated lead',
                'pipeline_id' => 10,
                'status_id' => 20,
                'responsible_user_id' => 30,
                'created_at' => 1781451600,
                'updated_at' => 1781455200,
                'custom_fields_values' => [['field_id' => 1, 'values' => [['value' => 'A']]]],
                '_embedded' => ['contacts' => [['id' => 500]]],
            ]);

        (new AmoWebhookService($http))->process($event);

        $event->refresh();
        $this->assertSame(AmoWebhookEvent::STATUS_PROCESSED, $event->status);
        $this->assertDatabaseHas('crm_entity_snapshots', [
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'external_id' => '100',
            'name' => 'Updated lead',
            'pipeline_id' => 10,
            'status_id' => 20,
            'responsible_user_id' => 30,
        ]);
    }

    public function test_amo_webhook_service_deletes_snapshot_for_delete_event(): void
    {
        $account = $this->accountWithToken($this->longLivedJwt());
        CrmEntitySnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'external_id' => '100',
            'name' => 'Deleted lead',
            'raw' => [],
            'synced_at' => now(),
        ]);
        $event = AmoWebhookEvent::query()->create([
            'amo_account_id' => $account->id,
            'event_type' => 'leads.delete',
            'entity_type' => 'leads',
            'entity_id' => '100',
            'payload' => ['id' => 100],
            'status' => AmoWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);
        $http = Mockery::mock(AmoFallbackHttpClient::class);
        $http->shouldNotReceive('get');

        (new AmoWebhookService($http))->process($event);

        $event->refresh();
        $this->assertSame(AmoWebhookEvent::STATUS_PROCESSED, $event->status);
        $this->assertDatabaseMissing('crm_entity_snapshots', [
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'external_id' => '100',
        ]);
    }

    public function test_crm_audit_sync_refreshes_recruiter_dashboard_cache(): void
    {
        Cache::flush();

        $account = $this->accountWithToken('abcdef123456');
        CrmCustomFieldSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'amo_field_id' => 777,
            'name' => 'Рекрутер',
            'field_type' => 'select',
            'enums' => [['id' => 1001, 'value' => 'Иван Рекрутер']],
            'raw' => [],
            'synced_at' => now(),
        ]);

        $statistics = app(AmoTaskStatisticsService::class);
        $from = now()->subDays(7);
        $to = now();
        $config = [
            'pipeline_id' => 10,
            'pipeline_name' => 'Массовый подбор',
            'recruiter_field_id' => 777,
            'recruiter_field_name' => 'Рекрутер',
        ];

        $this->assertSame(0, $statistics->recruiterLeadDistribution($account, $from, $to, $config)['assigned_leads_count']);

        $http = Mockery::mock(AmoFallbackHttpClient::class);
        $http->shouldReceive('get')->with($account, '/api/v4/leads/pipelines', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['pipelines' => [[
                'id' => 10,
                'name' => 'Массовый подбор',
                'sort' => 1,
                '_embedded' => ['statuses' => [['id' => 20, 'name' => 'New', 'sort' => 10]]],
            ]]],
        ]);
        foreach (['leads', 'contacts', 'companies'] as $entityType) {
            $http->shouldReceive('get')->with($account, "/api/v4/{$entityType}/custom_fields", Mockery::any())->andReturn([
                '_page' => 1,
                '_embedded' => ['custom_fields' => []],
            ]);
        }
        foreach ([
            ['/api/v4/leads/loss_reasons', 'loss_reasons'],
            ['/api/v4/sources', 'sources'],
            ['/api/v4/catalogs', 'catalogs'],
        ] as [$path, $key]) {
            $http->shouldReceive('get')->with($account, $path, Mockery::any())->andReturn([
                '_page' => 1,
                '_embedded' => [$key => []],
            ]);
        }
        $http->shouldReceive('get')->with($account, '/api/v4/leads', Mockery::any())->andReturn([
            '_page' => 1,
            '_embedded' => ['leads' => [[
                'id' => 100,
                'name' => 'Lead',
                'pipeline_id' => 10,
                'status_id' => 20,
                'created_at' => now()->subDay()->timestamp,
                'custom_fields_values' => [[
                    'field_id' => 777,
                    'field_name' => 'Рекрутер',
                    'values' => [['enum_id' => 1001, 'value' => 'Иван Рекрутер']],
                ]],
            ]]],
        ]);

        (new CrmAuditService($http))->syncAll($account, $from, $to, 10);

        $this->assertSame(1, $statistics->recruiterLeadDistribution($account, $from, $to, $config)['assigned_leads_count']);
    }

    public function test_oauth_refresh_saves_new_refresh_token(): void
    {
        $this->markTestSkipped('Requires official amoCRM OAuth client network flow; covered by integration testing with real credentials.');
    }

    public function test_fallback_http_client_truncates_large_api_log_payloads(): void
    {
        config(['amo.api_log_payload_max_bytes' => 512]);

        $account = $this->accountWithToken($this->longLivedJwt());
        Http::fake([
            'client.amocrm.ru/api/v4/leads*' => Http::response([
                '_page' => 1,
                '_page_count' => 1,
                '_embedded' => [
                    'leads' => collect(range(1, 100))->map(fn (int $id): array => [
                        'id' => $id,
                        'name' => str_repeat('Lead ', 40),
                    ])->all(),
                ],
                'access_token' => 'secret-token',
            ], 200),
        ]);

        app(AmoFallbackHttpClient::class)->get($account, '/api/v4/leads', [
            'filter' => ['access_token' => 'secret-query-token'],
        ]);

        $log = $account->apiRequestLogs()->firstOrFail();

        $this->assertSame(['access_token' => '[redacted]'], $log->request_payload['filter']);
        $this->assertTrue($log->response_payload['_truncated']);
        $this->assertGreaterThan(512, $log->response_payload['_original_bytes']);
        $this->assertContains('_embedded', $log->response_payload['_top_level_keys']);
        $this->assertStringNotContainsString('secret-token', json_encode($log->response_payload));
        $this->assertStringNotContainsString('Lead Lead Lead', json_encode($log->response_payload));
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

    private function completedTaskSnapshot(AmoAccount $account, string $externalId, int $responsibleUserId): CrmEntitySnapshot
    {
        return CrmEntitySnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'tasks',
            'external_id' => $externalId,
            'name' => 'Task',
            'responsible_user_id' => $responsibleUserId,
            'entity_created_at' => now()->subDays(2),
            'entity_updated_at' => now()->subDay(),
            'raw' => [
                'id' => (int) $externalId,
                'is_completed' => true,
                'complete_till' => now()->subDays(2)->timestamp,
                '_task_statistics' => ['completed_at' => now()->subDay()->timestamp],
            ],
            'synced_at' => now(),
        ]);
    }
}
