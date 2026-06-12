<?php

namespace Tests\Feature;

use App\Models\AmoAccount;
use App\Models\AmoAccountDashboardWidget;
use App\Models\AmoCredential;
use App\Models\AmoOAuthConnection;
use App\Models\AmoRolesSnapshot;
use App\Models\AmoUsersSnapshot;
use App\Models\ApiRequestLog;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmCustomFieldSnapshot;
use App\Models\CrmPipelineSnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Models\DashboardWidget;
use App\Models\IntegrationModule;
use App\Jobs\SyncCrmAuditJob;
use App\Jobs\SyncAmoTaskStatisticsJob;
use App\Models\LeadSyncSchedule;
use App\Models\ResponsibilityRedistributionRun;
use App\Models\TaskStatisticsSyncRun;
use App\Models\User;
use App\Services\Amo\AmoFallbackHttpClient;
use App\Services\Amo\AmoCatalogsService;
use App\Services\Amo\AmoLeadTransferService;
use App\Services\Amo\AmoOAuthTokenExchanger;
use App\Services\Amo\AmoPipelinesService;
use App\Services\Amo\AmoResponsibilityRedistributionService;
use App\Services\Amo\AmoTaskStatisticsService;
use App\Services\Amo\AmoUsersService;
use App\Services\Amo\CrmAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use Tests\TestCase;

class AuthAndAmoAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_page_renders_inertia_and_registration_stays_disabled(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->where('registrationEnabled', false)
                ->has('links.login'));

        $this->get('/register')->assertNotFound();
    }

    public function test_inertia_react_stack_returns_shared_account_context(): void
    {
        Route::middleware('web')->get('/__inertia-probe/{amo_account}', fn (AmoAccount $amo_account) => Inertia::render('System/InertiaProbe', [
            'probe' => true,
        ]));

        $user = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $this->actingAs($user)
            ->get("/__inertia-probe/{$account->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('System/InertiaProbe')
                ->where('currentAmoAccount.id', $account->id)
                ->where('auth.user.email', $user->email)
                ->where('probe', true));
    }

    public function test_manual_amo_account_creation_is_not_available(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $account->credentials()->create(['auth_type' => AmoCredential::AUTH_LONG_LIVED, 'access_token' => 'abcdef1234567890']);

        $this->actingAs($admin)->get('/amo-accounts/create')->assertNotFound();
        $this->actingAs($admin)->get('/amo-accounts')
            ->assertOk()
            ->assertDontSee('Добавить вручную')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Index')
                ->where('can.create', true)
                ->where('accounts.data.0.name', 'Client')
                ->where('accounts.data.0.base_domain', 'client.amocrm.ru')
                ->where('accounts.data.0.auth_type', AmoCredential::AUTH_LONG_LIVED)
                ->where('accounts.data.0.can.sync', true)
                ->has('links.install'));
    }

    public function test_viewer_cannot_edit_amo_account(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $this->actingAs($viewer)->put("/amo-accounts/{$account->id}", [
            'name' => 'Changed',
            'base_domain' => 'client.amocrm.ru',
            'auth_type' => AmoCredential::AUTH_LONG_LIVED,
            'is_active' => '1',
        ])->assertForbidden();
    }

    public function test_admin_can_edit_amo_account(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $account->credentials()->create(['auth_type' => AmoCredential::AUTH_LONG_LIVED, 'access_token' => 'abcdef1234567890']);

        $this->actingAs($admin)->put("/amo-accounts/{$account->id}", [
            'name' => 'Changed',
            'base_domain' => 'client.amocrm.ru',
            'auth_type' => AmoCredential::AUTH_LONG_LIVED,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('amo_accounts', ['id' => $account->id, 'name' => 'Changed']);
    }

    public function test_secrets_are_masked_and_not_rendered(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $account->credentials()->create(['auth_type' => AmoCredential::AUTH_LONG_LIVED, 'access_token' => 'abcdef1234567890']);

        $this->actingAs($admin)->get("/amo-accounts/{$account->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Edit')
                ->where('credential.masked_access_token', 'abcdef******7890')
                ->where('account.name', 'Client'))
            ->assertDontSee('abcdef1234567890');
    }

    public function test_secrets_are_not_stored_in_api_logs(): void
    {
        ApiRequestLog::query()->create([
            'method' => 'POST',
            'url' => 'https://client.amocrm.ru/api/v4/test',
            'request_payload' => ['access_token' => '[redacted]', 'client_secret' => '[redacted]'],
        ]);

        $this->assertDatabaseMissing('api_request_logs', ['request_payload' => json_encode(['access_token' => 'secret'])]);
    }

    public function test_account_dashboard_uses_selected_client_context(): void
    {
        $viewer = User::factory()->create();
        $first = AmoAccount::query()->create(['name' => 'First', 'base_domain' => 'first.amocrm.ru']);
        $second = AmoAccount::query()->create(['name' => 'Second', 'base_domain' => 'second.amocrm.ru']);

        AmoUsersSnapshot::query()->create([
            'amo_account_id' => $first->id,
            'amo_user_id' => 1,
            'name' => 'First Admin',
            'rights' => ['is_admin' => true],
            'is_admin' => true,
            'is_active' => true,
            'raw' => [],
            'synced_at' => now(),
        ]);
        AmoUsersSnapshot::query()->create([
            'amo_account_id' => $second->id,
            'amo_user_id' => 2,
            'name' => 'Second User',
            'rights' => [],
            'is_admin' => false,
            'is_active' => true,
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->actingAs($viewer)->get("/amo-accounts/{$first->id}/dashboard")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard/Index')
                ->where('currentAccount.name', 'First')
                ->where('currentAccount.base_domain', 'first.amocrm.ru')
                ->where('summary.users_count', 1)
                ->where('summary.admins_count', 1)
                ->has('links.current_account.users'));
    }

    public function test_can_open_second_amo_account_page(): void
    {
        $viewer = User::factory()->create();
        AmoAccount::query()->create(['name' => 'First', 'base_domain' => 'first.amocrm.ru']);
        $second = AmoAccount::query()->create([
            'name' => 'Second',
            'base_domain' => 'second.amocrm.ru',
            'settings' => ['company_name' => 'Second Company', 'timezone' => 'Europe/Moscow', 'currency' => 'RUB'],
        ]);
        $second->credentials()->create(['auth_type' => AmoCredential::AUTH_OAUTH]);

        $this->actingAs($viewer)
            ->get("/amo-accounts/{$second->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Show')
                ->where('account.name', 'Second')
                ->where('account.base_domain', 'second.amocrm.ru')
                ->where('account.settings.company_name', 'Second Company')
                ->where('account.settings.timezone', 'Europe/Moscow')
                ->where('account.auth_type', AmoCredential::AUTH_OAUTH)
                ->where('can.update', false)
                ->has('links.current_account.pipelines'));
    }

    public function test_admin_can_open_pipeline_create_form_and_viewer_cannot_create_pipeline(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $createPayload = null;
        $pipelinesService = Mockery::mock(AmoPipelinesService::class);
        $pipelinesService->shouldReceive('defaultStatuses')
            ->once()
            ->andReturn([
                ['name' => 'Первичный контакт', 'sort' => 10, 'color' => '#98cbff'],
                ['id' => 142, 'name' => 'Успешно реализовано'],
            ]);
        $pipelinesService->shouldReceive('createPipeline')
            ->once()
            ->with(Mockery::on(fn (AmoAccount $routeAccount): bool => $routeAccount->is($account)), Mockery::on(function (array $payload) use (&$createPayload): bool {
                $createPayload = $payload;

                return true;
            }))
            ->andReturn(['_embedded' => ['pipelines' => [['id' => 123]]]]);
        $this->app->instance(AmoPipelinesService::class, $pipelinesService);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/pipelines/create")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Pipelines/Create')
                ->where('account.name', 'Client')
                ->where('defaultStatuses.0.name', 'Первичный контакт')
                ->has('links.store'));

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/pipelines", [
                'name' => 'Pipeline',
                'sort' => 20,
                'is_unsorted_on' => '1',
                'statuses' => [
                    ['name' => 'Первичный контакт', 'hint' => 'Проверить источник и бюджет', 'sort' => 10, 'color' => '#99ccff'],
                    ['id' => 142, 'name' => 'Успешно реализовано'],
                ],
            ])
            ->assertRedirect(route('amo-accounts.pipelines.index', $account));

        $this->assertArrayHasKey('descriptions', $createPayload['statuses'][0], var_export($createPayload, true));
        $this->assertSame('newbie', $createPayload['statuses'][0]['descriptions'][0]['level']);
        $this->assertSame('Проверить источник и бюджет', $createPayload['statuses'][0]['descriptions'][0]['description']);
        $this->assertArrayNotHasKey('hint', $createPayload['statuses'][0]);

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/pipelines", [
                'name' => 'Pipeline',
                'sort' => 20,
                'is_unsorted_on' => '1',
                'statuses' => [
                    ['name' => 'Первичный контакт', 'sort' => 10, 'color' => '#99ccff'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_viewer_can_open_pipeline_settings_page(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $pipelinesService = Mockery::mock(AmoPipelinesService::class);
        $pipelinesService->shouldReceive('fetchPipelineDetails')
            ->once()
            ->with(Mockery::type(AmoAccount::class), 10)
            ->andReturn([
                'pipeline' => ['id' => 10, 'name' => 'Sales', 'is_main' => true],
                'statuses' => [['id' => 20, 'name' => 'New', 'sort' => 10, 'color' => '#99ccff']],
                'stage_rows' => [[
                    'status' => ['id' => 20, 'name' => 'New', 'sort' => 10, 'color' => '#99ccff'],
                    'description' => null,
                    'required_fields' => [['id' => 100, 'name' => 'Project', 'type' => 'select']],
                    'sources' => [['id' => 200, 'name' => 'Website']],
                ]],
                'lead_custom_fields' => [],
                'sources' => [['id' => 200, 'name' => 'Website']],
                'all_sources' => [['id' => 200, 'name' => 'Website']],
                'widgets' => [],
                'website_buttons' => [],
                'all_website_buttons' => [],
                'loss_reasons' => [],
                'errors' => [],
                'limitations' => [],
            ]);
        $this->app->instance(AmoPipelinesService::class, $pipelinesService);

        $this->actingAs($viewer)
            ->get("/amo-accounts/{$account->id}/pipelines/10")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Pipelines/Show')
                ->where('details.pipeline.name', 'Sales')
                ->where('details.stage_rows.0.required_fields.0.name', 'Project')
                ->where('details.stage_rows.0.sources.0.name', 'Website')
                ->where('can.sync', false));
    }

    public function test_pipeline_list_filters_archived_and_exports_current_filter(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $pipelinesService = Mockery::mock(AmoPipelinesService::class);
        $pipelinesService->shouldReceive('fetchPipelines')
            ->twice()
            ->with(Mockery::type(AmoAccount::class))
            ->andReturn([
                ['id' => 10, 'name' => 'Active Pipeline', 'is_archive' => false, '_embedded' => ['statuses' => []]],
                ['id' => 20, 'name' => 'Archived Pipeline', 'is_archive' => true, '_embedded' => ['statuses' => []]],
            ]);
        $this->app->instance(AmoPipelinesService::class, $pipelinesService);

        $this->actingAs($viewer)
            ->get("/amo-accounts/{$account->id}/pipelines?activity=active")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Pipelines/Index')
                ->where('account.name', 'Client')
                ->where('filters.activity', 'active')
                ->where('pipelines.0.name', 'Active Pipeline')
                ->where('pipelines.0.is_archive', false)
                ->missing('pipelines.1'));

        $response = $this->actingAs($viewer)
            ->get("/amo-accounts/{$account->id}/pipelines-export?activity=archived");

        $response->assertOk();
        $this->assertStringContainsString('amo-pipelines-', $response->headers->get('content-disposition'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Archived Pipeline', $content);
        $this->assertStringNotContainsString('Active Pipeline', $content);
    }

    public function test_admin_can_transfer_leads_between_pipelines_and_viewer_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        foreach ([[10, 'Old'], [20, 'New']] as [$pipelineId, $name]) {
            CrmPipelineSnapshot::query()->create([
                'amo_account_id' => $account->id,
                'amo_pipeline_id' => $pipelineId,
                'name' => $name,
                'is_archive' => false,
                'raw' => [],
                'synced_at' => now(),
            ]);
        }
        CrmPipelineStatusSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'amo_status_id' => 101,
            'name' => 'Первичный контакт',
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmPipelineStatusSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 20,
            'amo_status_id' => 201,
            'name' => 'Первичный контакт',
            'raw' => [],
            'synced_at' => now(),
        ]);

        $transferService = Mockery::mock(AmoLeadTransferService::class);
        $transferService->shouldReceive('plan')
            ->once()
            ->with(Mockery::on(fn (AmoAccount $routeAccount): bool => $routeAccount->is($account)), 10, 20, [])
            ->andReturn([
                'rows' => [[
                    'source_status_id' => 101,
                    'source_status_name' => 'Первичный контакт',
                    'target_status_id' => 201,
                    'target_status_name' => 'Первичный контакт',
                    'lead_count' => 2,
                    'can_transfer' => true,
                ]],
                'total_leads' => 2,
                'transferable_leads' => 2,
                'blocked_leads' => 0,
            ]);
        $transferService->shouldReceive('transfer')
            ->once()
            ->with(Mockery::on(fn (AmoAccount $routeAccount): bool => $routeAccount->is($account)), 10, 20, [101 => 201])
            ->andReturn(['updated' => 2, 'skipped' => 0]);
        $this->app->instance(AmoLeadTransferService::class, $transferService);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/pipelines/transfer-leads?source_pipeline_id=10&target_pipeline_id=20")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Pipelines/TransferLeads')
                ->where('plan.transferable_leads', 2)
                ->has('links.submit'));

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/pipelines/transfer-leads", [
                'source_pipeline_id' => 10,
                'target_pipeline_id' => 20,
                'status_map' => [101 => 201],
            ])
            ->assertRedirect(route('amo-accounts.pipelines.transfer-leads', [
                'amo_account' => $account,
                'source_pipeline_id' => 10,
                'target_pipeline_id' => 20,
            ]));

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/pipelines/transfer-leads", [
                'source_pipeline_id' => 10,
                'target_pipeline_id' => 20,
                'status_map' => [101 => 201],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_preview_responsibility_redistribution(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $service = Mockery::mock(AmoResponsibilityRedistributionService::class);
        $service->shouldReceive('activeUsers')->twice()->with(Mockery::type(AmoAccount::class))->andReturn([
            ['id' => 10, 'name' => 'Old Manager', 'email' => 'old@example.test'],
            ['id' => 20, 'name' => 'New Manager A', 'email' => 'a@example.test'],
            ['id' => 30, 'name' => 'New Manager B', 'email' => 'b@example.test'],
        ]);
        $service->shouldReceive('preview')
            ->once()
            ->with(Mockery::type(AmoAccount::class), 10, ['20', '30'], true)
            ->andReturn([
                'source_user_id' => 10,
                'target_user_ids' => [20, 30],
                'include_tasks' => true,
                'contacts_count' => 3,
                'leads_count' => 4,
                'tasks_count' => 5,
                'by_target' => [
                    ['target_user_id' => 20, 'contacts_count' => 2, 'leads_count' => 3, 'tasks_count' => 4],
                    ['target_user_id' => 30, 'contacts_count' => 1, 'leads_count' => 1, 'tasks_count' => 1],
                ],
                'sample_contacts' => [],
            ]);
        $this->app->instance(AmoResponsibilityRedistributionService::class, $service);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/responsibility-redistribution")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/ResponsibilityRedistribution/Index')
                ->where('users.0.name', 'Old Manager')
                ->where('can.sync', true));

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/responsibility-redistribution/preview", [
                'source_user_id' => 10,
                'target_user_ids' => [20, 30],
                'include_tasks' => '1',
            ])
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/ResponsibilityRedistribution/Index')
                ->where('preview.contacts_count', 3)
                ->where('preview.tasks_count', 5)
                ->where('preview.by_target.0.contacts_count', 2));
    }

    public function test_admin_can_run_responsibility_redistribution_and_persist_result(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $service = Mockery::mock(AmoResponsibilityRedistributionService::class);
        $service->shouldReceive('preview')->once()->with(Mockery::type(AmoAccount::class), 10, ['20', '30'], true)->andReturn([
            'source_user_id' => 10,
            'target_user_ids' => [20, 30],
            'include_tasks' => true,
            'contacts_count' => 2,
            'leads_count' => 3,
            'tasks_count' => 4,
            'by_target' => [],
            'sample_contacts' => [],
        ]);
        $service->shouldReceive('redistribute')->once()->with(Mockery::type(AmoAccount::class), 10, ['20', '30'], true)->andReturn([
            'source_user_id' => 10,
            'target_user_ids' => [20, 30],
            'include_tasks' => true,
            'updated_contacts' => 2,
            'updated_leads' => 3,
            'updated_tasks' => 4,
            'remaining_contacts_count' => 0,
            'remaining_leads_count' => 0,
            'remaining_tasks_count' => 0,
            'remaining_contact_ids' => [],
            'remaining_lead_ids' => [],
            'remaining_task_ids' => [],
            'by_target' => [],
        ]);
        $this->app->instance(AmoResponsibilityRedistributionService::class, $service);

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/responsibility-redistribution", [
                'source_user_id' => 10,
                'target_user_ids' => [20, 30],
                'include_tasks' => '1',
            ])
            ->assertRedirect("/amo-accounts/{$account->id}/responsibility-redistribution");

        $run = ResponsibilityRedistributionRun::query()->firstOrFail();
        $this->assertSame(ResponsibilityRedistributionRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(2, $run->result['updated_contacts']);
        $this->assertSame(3, $run->result['updated_leads']);
        $this->assertSame(4, $run->result['updated_tasks']);
    }

    public function test_viewer_cannot_run_responsibility_redistribution(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/responsibility-redistribution", [
                'source_user_id' => 10,
                'target_user_ids' => [20],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_view_and_sync_task_statistics(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $service = Mockery::mock(AmoTaskStatisticsService::class);
        $service->shouldReceive('statistics')
            ->once()
            ->with(Mockery::type(AmoAccount::class), Mockery::any(), Mockery::any())
            ->andReturn([[
                'responsible_user_id' => 10,
                'responsible_name' => 'Manager',
                'completed_count' => 5,
                'open_count' => 4,
                'overdue_count' => 1,
                'total_count' => 9,
                'overdue_rate' => 25.0,
            ]]);
        $this->app->instance(AmoTaskStatisticsService::class, $service);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/task-statistics?from=2026-06-01&to=2026-06-09")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/TaskStatistics/Index')
                ->where('rows.0.responsible_name', 'Manager')
                ->where('rows.0.completed_count', 5)
                ->where('can.sync', true));

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/task-statistics/sync", [
                'from' => '2026-01-01',
                'to' => '2026-06-09',
            ])
            ->assertRedirect("/amo-accounts/{$account->id}/task-statistics?from=2026-01-01&to=2026-06-09");

        $run = TaskStatisticsSyncRun::query()->firstOrFail();
        $this->assertSame(TaskStatisticsSyncRun::STATUS_PENDING, $run->status);
        $this->assertSame('2026-04-26', $run->period_from->toDateString());
        $this->assertSame('2026-06-09', $run->period_to->toDateString());
        Queue::assertPushed(SyncAmoTaskStatisticsJob::class);

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/task-statistics/sync", [
                'from' => '2026-06-01',
                'to' => '2026-06-09',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_view_event_sync_coverage_and_start_45_day_sync(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));

        try {
            $admin = User::factory()->admin()->create();
            $viewer = User::factory()->create();
            $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
            $account->markTaskStatisticsSyncedUntil(Carbon::parse('2026-06-10 10:00:00'));
            AmoUsersSnapshot::query()->create([
                'amo_account_id' => $account->id,
                'amo_user_id' => 10,
                'name' => 'Avito Manager',
                'rights' => [],
                'group_id' => 30,
                'is_admin' => false,
                'is_active' => true,
                'raw' => [],
                'synced_at' => now(),
            ]);

            CrmEntitySnapshot::query()->create([
                'amo_account_id' => $account->id,
                'entity_type' => 'events',
                'external_id' => 'event-1',
                'name' => 'lead_status_changed',
                'responsible_user_id' => 10,
                'entity_created_at' => Carbon::parse('2026-06-01 10:00:00'),
                'entity_updated_at' => Carbon::parse('2026-06-01 10:00:00'),
                'raw' => ['id' => 'event-1', 'entity_id' => 100, 'entity_type' => 'lead'],
                'synced_at' => Carbon::parse('2026-06-01 10:01:00'),
            ]);
            CrmEntitySnapshot::query()->create([
                'amo_account_id' => $account->id,
                'entity_type' => 'events',
                'external_id' => 'event-2',
                'name' => 'lead_status_changed',
                'responsible_user_id' => 11,
                'entity_created_at' => Carbon::parse('2026-06-05 11:00:00'),
                'entity_updated_at' => Carbon::parse('2026-06-05 11:00:00'),
                'raw' => ['id' => 'event-2', 'entity_id' => 101, 'entity_type' => 'lead'],
                'synced_at' => Carbon::parse('2026-06-05 11:01:00'),
            ]);

            $this->actingAs($admin)
                ->get("/amo-accounts/{$account->id}/events-sync")
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('AmoAccounts/EventsSync/Index')
                    ->where('coverage.events_count', 2)
                    ->where('coverage.period_from', '2026-06-01 10:00:00')
                    ->where('coverage.period_to', '2026-06-05 11:00:00')
                    ->where('coverage.cursor', '2026-06-10 10:00:00')
                    ->where('groups.0.id', 30)
                    ->where('groups.0.name', 'Группа 30')
                    ->where('can.sync', true));

            $this->actingAs($admin)
                ->post("/amo-accounts/{$account->id}/events-sync/settings", [
                    'avito_recruiting_group_id_manual' => 30,
                ])
                ->assertRedirect("/amo-accounts/{$account->id}/events-sync");

            $this->assertSame(30, (int) data_get($account->refresh()->settings, 'reports.avito_recruiting_group_id'));

            $this->actingAs($admin)
                ->post("/amo-accounts/{$account->id}/events-sync")
                ->assertRedirect("/amo-accounts/{$account->id}/events-sync");

            $run = TaskStatisticsSyncRun::query()->firstOrFail();
            $this->assertSame('2026-04-27 00:00:00', $run->period_from->format('Y-m-d H:i:s'));
            $this->assertSame('2026-06-10 23:59:59', $run->period_to->format('Y-m-d H:i:s'));
            Queue::assertPushed(SyncAmoTaskStatisticsJob::class);

            $this->actingAs($viewer)
                ->post("/amo-accounts/{$account->id}/events-sync")
                ->assertForbidden();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_users_export_respects_current_filters(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        AmoUsersSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_user_id' => 1,
            'name' => 'Visible Admin',
            'email' => 'visible@example.test',
            'rights' => ['is_admin' => true, 'leads' => ['view' => 'A']],
            'is_admin' => true,
            'is_active' => true,
            'role_id' => 10,
            'group_id' => 20,
            'raw' => ['id' => 1, 'name' => 'Visible Admin'],
            'synced_at' => now(),
        ]);
        AmoUsersSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_user_id' => 2,
            'name' => 'Hidden User',
            'email' => 'hidden@example.test',
            'rights' => [],
            'is_admin' => false,
            'is_active' => true,
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get("/amo-accounts/{$account->id}/users?admins=1")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Users')
                ->where('account.name', 'Client')
                ->where('filters.admins', true)
                ->where('users.data.0.name', 'Visible Admin')
                ->where('users.data.0.role_id', 10)
                ->where('users.data.0.group_id', 20)
                ->where('users.data.0.rights.leads.view', 'A')
                ->missing('users.data.1'));

        $response = $this->actingAs($viewer)
            ->get("/amo-accounts/{$account->id}/users-export?admins=1");

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Visible Admin', $content);
        $this->assertStringNotContainsString('Hidden User', $content);
    }

    public function test_roles_page_renders_inertia_and_exports_roles(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        AmoRolesSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_role_id' => 100,
            'name' => 'Managers',
            'rights' => ['leads' => ['view' => 'A']],
            'users' => [['id' => 1], ['id' => 2]],
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get("/amo-accounts/{$account->id}/roles")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Roles')
                ->where('account.name', 'Client')
                ->where('roles.data.0.name', 'Managers')
                ->where('roles.data.0.users_count', 2)
                ->where('roles.data.0.rights.leads.view', 'A'));

        $response = $this->actingAs($viewer)->get("/amo-accounts/{$account->id}/roles-export");

        $response->assertOk();
        $this->assertStringContainsString('Managers', $response->streamedContent());
    }

    public function test_integrations_and_widgets_pages_render_inertia(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        IntegrationModule::query()->create([
            'code' => 'users_audit',
            'name' => 'Users audit',
            'description' => 'Audit users',
            'is_enabled' => true,
        ]);

        DashboardWidget::query()->create([
            'code' => 'users_count',
            'name' => 'Users count',
            'component_key' => 'metric.users',
            'sort_order' => 10,
            'is_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/integrations")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Integrations')
                ->where('account.name', 'Client')
                ->where('modules.0.code', 'users_audit')
                ->where('modules.0.is_enabled', true)
                ->where('can.sync', true)
                ->has('links.current_account.users'));

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/widgets")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Widgets')
                ->where('account.name', 'Client')
                ->where('widgets.0.code', 'users_count')
                ->where('widgets.0.component_key', 'metric.users')
                ->has('widgets.0.installation.public_key')
                ->has('widgets.0.installation.settings_url'));
    }

    public function test_admin_can_configure_dashboard_widget_report_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $widget = DashboardWidget::query()->create([
            'code' => 'task_overdue_dashboard',
            'name' => 'Просроченные выполненные задачи',
            'component_key' => 'amo_iframe_task_overdue_dashboard',
            'sort_order' => 70,
            'is_enabled' => true,
        ]);
        CrmPipelineSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'name' => 'Массовый подбор',
            'raw' => [],
            'synced_at' => now(),
        ]);
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
        CrmCustomFieldSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'amo_field_id' => 778,
            'name' => 'Менеджер',
            'field_type' => 'select',
            'enums' => [['id' => 2001, 'value' => 'Первый менеджер']],
            'raw' => [],
            'synced_at' => now(),
        ]);
        foreach ([[779, 'Команда'], [780, 'Город'], [781, 'Источник']] as [$fieldId, $name]) {
            CrmCustomFieldSnapshot::query()->create([
                'amo_account_id' => $account->id,
                'entity_type' => 'leads',
                'amo_field_id' => $fieldId,
                'name' => $name,
                'field_type' => 'select',
                'raw' => [],
                'synced_at' => now(),
            ]);
        }
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
                'values' => [['enum_id' => 1001, 'value' => 'Иван Рекрутер']],
            ]],
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/widgets/{$widget->id}/settings")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Widgets/Settings')
                ->where('pipelines.0.id', 10)
                ->has('leadFields', 5)
                ->where('diagnostics.synced_leads_total', 1)
                ->where('diagnostics.field_found', true));

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/widgets/{$widget->id}/settings", [
                'pipeline_id' => 10,
                'recruiter_field_id' => 777,
                'manager_field_id' => 778,
                'team_field_id' => 779,
                'city_field_id' => 780,
                'source_field_id' => 781,
            ])
            ->assertRedirect("/amo-accounts/{$account->id}/widgets/{$widget->id}/settings");

        $installation = AmoAccountDashboardWidget::query()->where('amo_account_id', $account->id)->where('dashboard_widget_id', $widget->id)->firstOrFail();
        $this->assertSame(10, $installation->config['pipeline_id']);
        $this->assertSame('Массовый подбор', $installation->config['pipeline_name']);
        $this->assertSame(777, $installation->config['recruiter_field_id']);
        $this->assertSame('Рекрутер', $installation->config['recruiter_field_name']);
        $this->assertSame(778, $installation->config['manager_field_id']);
        $this->assertSame('Менеджер', $installation->config['manager_field_name']);
        $this->assertSame(779, $installation->config['team_field_id']);
        $this->assertSame('Команда', $installation->config['team_field_name']);
        $this->assertSame(780, $installation->config['city_field_id']);
        $this->assertSame('Город', $installation->config['city_field_name']);
        $this->assertSame(781, $installation->config['source_field_id']);
        $this->assertSame('Источник', $installation->config['source_field_name']);

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/widgets/{$widget->id}/settings", [
                'pipeline_id' => 10,
                'recruiter_field_id' => 777,
                'manager_field_id' => 778,
                'team_field_id' => 779,
                'city_field_id' => 780,
                'source_field_id' => 781,
            ])
            ->assertForbidden();
    }

    public function test_recruiter_report_debug_command_outputs_local_diagnostics(): void
    {
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $widget = DashboardWidget::query()->create([
            'code' => 'task_overdue_dashboard',
            'name' => 'Просроченные выполненные задачи',
            'component_key' => 'amo_iframe_task_overdue_dashboard',
            'sort_order' => 70,
            'is_enabled' => true,
        ]);
        AmoAccountDashboardWidget::query()->create([
            'amo_account_id' => $account->id,
            'dashboard_widget_id' => $widget->id,
            'public_key' => 'public-widget-key',
            'is_enabled' => true,
            'config' => [
                'pipeline_id' => 10,
                'pipeline_name' => 'Массовый подбор',
                'recruiter_field_id' => 777,
                'recruiter_field_name' => 'Рекрутер',
                'manager_field_id' => 778,
                'manager_field_name' => 'Менеджер',
                'team_field_id' => 779,
                'team_field_name' => 'Команда',
                'city_field_id' => 780,
                'city_field_name' => 'Город',
            ],
        ]);
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
                'values' => [['enum_id' => 1001, 'value' => 'Иван Рекрутер']],
            ]],
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->artisan('amo:debug-recruiter-report', ['accountId' => $account->id])
            ->expectsOutputToContain('Client')
            ->expectsOutputToContain('pipeline_period_leads_total')
            ->expectsOutputToContain('Иван Рекрутер')
            ->assertSuccessful();
    }

    public function test_public_task_overdue_widget_uses_account_installation_key(): void
    {
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $account->forceFill(['settings' => ['reports' => ['avito_recruiting_group_id' => 30]]])->save();
        $widget = DashboardWidget::query()->create([
            'code' => 'task_overdue_dashboard',
            'name' => 'Просроченные выполненные задачи',
            'component_key' => 'amo_iframe_task_overdue_dashboard',
            'sort_order' => 70,
            'is_enabled' => true,
        ]);
        $installation = AmoAccountDashboardWidget::query()->create([
            'amo_account_id' => $account->id,
            'dashboard_widget_id' => $widget->id,
            'public_key' => 'public-widget-key',
            'is_enabled' => true,
            'config' => [
                'pipeline_id' => 10,
                'pipeline_name' => 'Массовый подбор',
                'recruiter_field_id' => 777,
                'recruiter_field_name' => 'Рекрутер',
                'source_field_id' => 781,
                'source_field_name' => 'Источник',
            ],
        ]);
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
        CrmCustomFieldSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'amo_field_id' => 777,
            'name' => 'Рекрутер',
            'field_type' => 'select',
            'enums' => [
                ['id' => 1001, 'value' => 'Иван Рекрутер', 'sort' => 0],
                ['id' => 1002, 'value' => 'Мария Рекрутер', 'sort' => 1],
                ['id' => 1003, 'value' => 'Пустой рекрутер', 'sort' => 2],
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
                ['id' => 2001, 'value' => 'Первый менеджер', 'sort' => 0],
                ['id' => 2002, 'value' => 'Второй менеджер', 'sort' => 1],
            ],
            'raw' => [],
            'synced_at' => now(),
        ]);
        foreach ([
            [779, 'Команда', [['id' => 3001, 'value' => 'Альфа'], ['id' => 3002, 'value' => 'Бетта']]],
            [780, 'Город', [['id' => 4001, 'value' => 'Москва'], ['id' => 4002, 'value' => 'Омск']]],
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
            ['id' => 501, 'name' => 'Lead 1', 'pipeline_id' => 10, 'status_id' => 111, 'recruiter_enum_id' => 1001, 'recruiter' => 'Иван Рекрутер', 'manager' => 'Первый менеджер', 'team' => 'Альфа', 'city' => 'Москва', 'source' => 'Авито'],
            ['id' => 502, 'name' => 'Lead 2', 'pipeline_id' => 10, 'status_id' => 142, 'recruiter_enum_id' => 1001, 'recruiter' => 'Иван Рекрутер', 'manager' => null, 'team' => 'Альфа', 'city' => 'Москва', 'source' => 'Сайт'],
            ['id' => 503, 'name' => 'Lead 3', 'pipeline_id' => 10, 'status_id' => 143, 'recruiter_enum_id' => 1002, 'recruiter' => 'Мария Рекрутер', 'manager' => 'Второй менеджер', 'team' => 'Бетта', 'city' => 'Омск', 'source' => 'Сайт'],
            ['id' => 504, 'name' => 'Other Pipeline Lead', 'pipeline_id' => 20, 'status_id' => 111, 'recruiter_enum_id' => 1002, 'recruiter' => 'Мария Рекрутер', 'manager' => 'Второй менеджер', 'team' => 'Бетта', 'city' => 'Омск', 'source' => 'Авито'],
        ] as $lead) {
            CrmEntitySnapshot::query()->create([
                'amo_account_id' => $account->id,
                'entity_type' => 'leads',
                'external_id' => (string) $lead['id'],
                'name' => $lead['name'],
                'pipeline_id' => $lead['pipeline_id'],
                'status_id' => $lead['status_id'],
                'entity_created_at' => now()->subDay(),
                'custom_fields_values' => [[
                    'field_id' => 777,
                    'field_name' => 'Рекрутер',
                    'values' => [[
                        'enum_id' => $lead['recruiter_enum_id'],
                        'value' => $lead['recruiter'],
                    ]],
                ], [
                    'field_id' => 778,
                    'field_name' => 'Менеджер',
                    'values' => $lead['manager'] === null ? [] : [[
                        'value' => $lead['manager'],
                    ]],
                ], [
                    'field_id' => 779,
                    'field_name' => 'Команда',
                    'values' => [['value' => $lead['team']]],
                ], [
                    'field_id' => 780,
                    'field_name' => 'Город',
                    'values' => [['value' => $lead['city']]],
                ], [
                    'field_id' => 781,
                    'field_name' => 'Источник',
                    'values' => [['value' => $lead['source']]],
                ]],
                'raw' => [],
                'synced_at' => now(),
            ]);
        }
        CrmEntitySnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'tasks',
            'external_id' => '100',
            'name' => 'Task',
            'responsible_user_id' => 10,
            'entity_created_at' => now()->subDays(2),
            'entity_updated_at' => now()->subDay(),
            'raw' => [
                'id' => 100,
                'is_completed' => true,
                'complete_till' => now()->subDays(2)->timestamp,
                '_task_statistics' => ['completed_at' => now()->subDay()->timestamp],
            ],
            'synced_at' => now(),
        ]);

        $this->get("/api/widgets/amo/{$installation->public_key}/task-overdue-dashboard")
            ->assertOk()
            ->assertJsonPath('account.name', 'Client')
            ->assertJsonPath('groups.0.group_name', 'Sales')
            ->assertJsonPath('groups.0.users.0.name', 'Manager')
            ->assertJsonPath('groups.0.users.0.completed_overdue_count', 1)
            ->assertJsonPath('recruiterLeads.field_name', 'Рекрутер')
            ->assertJsonPath('recruiterLeads.pipeline_id', 10)
            ->assertJsonPath('recruiterLeads.pipeline_name', 'Массовый подбор')
            ->assertJsonPath('recruiterLeads.total_leads_count', 3)
            ->assertJsonPath('recruiterLeads.assigned_leads_count', 3)
            ->assertJsonPath('recruiterLeads.transferred_to_manager_count', 2)
            ->assertJsonPath('recruiterLeads.recruiters.0.name', 'Иван Рекрутер')
            ->assertJsonPath('recruiterLeads.recruiters.0.leads_count', 2)
            ->assertJsonPath('recruiterLeads.recruiters.0.transferred_to_manager_count', 1)
            ->assertJsonPath('recruiterLeads.recruiters.1.name', 'Мария Рекрутер')
            ->assertJsonPath('recruiterLeads.recruiters.1.leads_count', 1)
            ->assertJsonPath('recruiterLeads.recruiters.1.transferred_to_manager_count', 1)
            ->assertJsonPath('recruiterLeads.recruiters.2.name', 'Пустой рекрутер')
            ->assertJsonPath('recruiterLeads.recruiters.2.leads_count', 0)
            ->assertJsonPath('recruiterTeamCityBreakdown.total_leads_count', 2)
            ->assertJsonPath('recruiterTeamCityBreakdown.source_field_found', true)
            ->assertJsonPath('recruiterTeamCityBreakdown.source_columns.0', 'Авито')
            ->assertJsonPath('recruiterTeamCityBreakdown.source_columns.1', 'Сайт')
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.0.name', 'Иван Рекрутер')
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.0.teams.0.name', 'Альфа')
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.0.teams.0.cities.0.name', 'Москва')
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.0.teams.0.cities.0.leads_count', 1)
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.0.teams.0.cities.0.sources.Авито', 1)
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.0.teams.0.cities.0.sources.Сайт', 0)
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.1.name', 'Мария Рекрутер')
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.1.teams.0.name', 'Бетта')
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.1.teams.0.cities.0.name', 'Омск')
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.1.teams.0.cities.0.leads_count', 1)
            ->assertJsonPath('recruiterTeamCityBreakdown.recruiters.1.teams.0.cities.0.sources.Сайт', 1);

        $this->get('/api/widgets/amo/wrong-key/task-overdue-dashboard')->assertNotFound();
    }

    public function test_public_task_widget_uses_amo_workspace_period_query(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00'));

        try {
            $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
            $widget = DashboardWidget::query()->create([
                'code' => 'task_overdue_dashboard',
                'name' => 'Просроченные выполненные задачи',
                'component_key' => 'amo_iframe_task_overdue_dashboard',
                'sort_order' => 70,
                'is_enabled' => true,
            ]);
            $installation = AmoAccountDashboardWidget::query()->create([
                'amo_account_id' => $account->id,
                'dashboard_widget_id' => $widget->id,
                'public_key' => 'public-widget-key',
                'is_enabled' => true,
            ]);

            $this->get("/api/widgets/amo/{$installation->public_key}/task-overdue-dashboard?currency=RUB&date_from=false&date_to=false&lang=ru&period=week&t=0.1768444589890391")
                ->assertOk()
                ->assertJsonPath('period.from', '2026-06-08')
                ->assertJsonPath('period.to', '2026-06-14')
                ->assertJsonPath('period.source', 'amo_period')
                ->assertJsonPath('period.preset', 'week')
                ->assertJsonPath('period.label', 'Эта неделя');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_public_task_widget_sets_frame_ancestors_csp(): void
    {
        config(['amo.widgets.frame_ancestors' => 'https://*.amocrm.ru https://*.kommo.com']);

        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $widget = DashboardWidget::query()->create([
            'code' => 'task_overdue_dashboard',
            'name' => 'Просроченные выполненные задачи',
            'component_key' => 'amo_iframe_task_overdue_dashboard',
            'sort_order' => 70,
            'is_enabled' => true,
        ]);
        $installation = AmoAccountDashboardWidget::query()->create([
            'amo_account_id' => $account->id,
            'dashboard_widget_id' => $widget->id,
            'public_key' => 'public-widget-key',
            'is_enabled' => true,
        ]);

        $this->get("/widgets/amo/{$installation->public_key}/task-overdue-dashboard")
            ->assertOk()
            ->assertHeader('Content-Security-Policy', 'frame-ancestors https://*.amocrm.ru https://*.kommo.com')
            ->assertHeaderMissing('X-Frame-Options');
    }

    public function test_task_dashboard_ui_keeps_task_and_lead_reports_separate(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Widgets/Amo/TaskOverdueDashboard.tsx'));

        $this->assertStringContainsString('Отчеты рабочего стола', $source);
        $this->assertStringContainsString('debug_iframe', $source);
        $this->assertStringContainsString('postMessage events', $source);
        $this->assertStringContainsString('Период с рабочего стола amoCRM', $source);
        $this->assertStringContainsString('Отчет по задачам', $source);
        $this->assertStringContainsString('Выполненные просроченные задачи', $source);
        $this->assertStringContainsString('Отчет по сделкам', $source);
        $this->assertStringContainsString('ReportSection', $source);
        $this->assertStringContainsString('MetricCard', $source);
        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white shadow-theme-sm', $source);
        $this->assertStringContainsString('Источник:', $source);
        $this->assertStringContainsString('source_columns.map', $source);
        $this->assertStringContainsString('city.sources[source]', $source);
        $this->assertLessThan(strpos($source, 'Отчет по задачам'), strpos($source, 'Передачи рекрутеров'));
        $this->assertLessThan(strpos($source, 'Отчет по задачам'), strpos($source, 'Отчет по сделкам'));
    }

    public function test_widget_settings_ui_allows_source_field_selection(): void
    {
        $source = file_get_contents(resource_path('js/Pages/AmoAccounts/Widgets/Settings.tsx'));

        $this->assertStringContainsString('Поле сделки с источником', $source);
        $this->assertStringContainsString('name="source_field_id"', $source);
        $this->assertStringContainsString('Авто: поле “Источник”', $source);
    }

    public function test_authenticated_layout_uses_tailadmin_shell(): void
    {
        $source = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.tsx'));

        $this->assertStringContainsString('lg:ml-[290px]', $source);
        $this->assertStringContainsString('menu-item-active', $source);
        $this->assertStringContainsString('custom-scrollbar', $source);
        $this->assertStringContainsString('aria-label="Toggle sidebar"', $source);
    }

    public function test_shared_react_components_use_tailadmin_visual_tokens(): void
    {
        $metric = file_get_contents(resource_path('js/Components/DashboardMetric.tsx'));
        $pagination = file_get_contents(resource_path('js/Components/Pagination.tsx'));
        $json = file_get_contents(resource_path('js/Components/JsonDetails.tsx'));
        $form = file_get_contents(resource_path('js/Components/PlainActionForm.tsx'));

        $this->assertStringContainsString('shadow-theme-sm', $metric);
        $this->assertStringContainsString('bg-brand-500', $pagination);
        $this->assertStringContainsString('text-brand-600', $json);
        $this->assertStringContainsString('shadow-theme-xs', $form);
    }

    public function test_dashboard_page_uses_tailadmin_table_and_actions(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Dashboard/Index.tsx'));

        $this->assertStringContainsString('Operations overview', $source);
        $this->assertStringContainsString('actionLinkClass', $source);
        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white shadow-theme-sm', $source);
        $this->assertStringContainsString('bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500', $source);
    }

    public function test_amo_accounts_index_uses_tailadmin_table_and_shared_pagination(): void
    {
        $source = file_get_contents(resource_path('js/Pages/AmoAccounts/Index.tsx'));

        $this->assertStringContainsString('Client connections', $source);
        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white shadow-theme-sm', $source);
        $this->assertStringContainsString('bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500', $source);
        $this->assertStringContainsString('<Pagination links={accounts.links} />', $source);
    }

    public function test_amo_account_show_uses_tailadmin_detail_layout(): void
    {
        $source = file_get_contents(resource_path('js/Pages/AmoAccounts/Show.tsx'));

        $this->assertStringContainsString('Client profile', $source);
        $this->assertStringContainsString('primaryButtonClass', $source);
        $this->assertStringContainsString('quickLinkClass', $source);
        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white shadow-theme-sm', $source);
    }

    public function test_amo_account_edit_uses_tailadmin_form_layout(): void
    {
        $source = file_get_contents(resource_path('js/Pages/AmoAccounts/Edit.tsx'));

        $this->assertStringContainsString('Connection settings', $source);
        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm', $source);
        $this->assertStringContainsString('focus:border-brand-300 focus:ring-brand-500/10', $source);
        $this->assertStringContainsString('text-theme-xs font-medium text-red-600', $source);
    }

    public function test_users_roles_and_api_logs_use_tailadmin_tables(): void
    {
        $users = file_get_contents(resource_path('js/Pages/AmoAccounts/Users.tsx'));
        $roles = file_get_contents(resource_path('js/Pages/AmoAccounts/Roles.tsx'));
        $logs = file_get_contents(resource_path('js/Pages/Logs/Api.tsx'));

        $this->assertStringContainsString('Users audit', $users);
        $this->assertStringContainsString('<Pagination links={users.links} />', $users);
        $this->assertStringContainsString('<JsonDetails data={user.raw} />', $users);
        $this->assertStringContainsString('Roles audit', $roles);
        $this->assertStringContainsString('<JsonDetails data={role.rights} />', $roles);
        $this->assertStringContainsString('System logs', $logs);
        $this->assertStringContainsString('<JsonDetails data={log.response_payload} />', $logs);
    }

    public function test_leads_and_pipelines_use_tailadmin_operational_tables(): void
    {
        $leads = file_get_contents(resource_path('js/Pages/AmoAccounts/Leads.tsx'));
        $pipelines = file_get_contents(resource_path('js/Pages/AmoAccounts/Pipelines/Index.tsx'));

        $this->assertStringContainsString('Leads analytics', $leads);
        $this->assertStringContainsString('<JsonDetails data={lead.raw} />', $leads);
        $this->assertStringContainsString('<Pagination links={leads.links} />', $leads);
        $this->assertStringContainsString('Pipeline settings', $pipelines);
        $this->assertStringContainsString('rounded-full border border-gray-200 bg-gray-50', $pipelines);
    }

    public function test_pipeline_show_uses_tailadmin_settings_tables(): void
    {
        $source = file_get_contents(resource_path('js/Pages/AmoAccounts/Pipelines/Show.tsx'));

        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm', $source);
        $this->assertStringContainsString('bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500', $source);
        $this->assertStringContainsString('rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs text-gray-700', $source);
        $this->assertStringContainsString('DataTable', $source);
    }

    public function test_pipeline_forms_use_tailadmin_controls(): void
    {
        $create = file_get_contents(resource_path('js/Pages/AmoAccounts/Pipelines/Create.tsx'));
        $clone = file_get_contents(resource_path('js/Pages/AmoAccounts/Pipelines/Clone.tsx'));
        $transfer = file_get_contents(resource_path('js/Pages/AmoAccounts/Pipelines/TransferLeads.tsx'));

        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm', $create);
        $this->assertStringContainsString('focus:border-brand-300 focus:ring-brand-500/10', $create);
        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm', $clone);
        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm', $transfer);
        $this->assertStringContainsString('inline-flex h-10 items-center rounded-lg bg-brand-500', $transfer);
    }

    public function test_analytics_pages_use_tailadmin_surfaces(): void
    {
        $crmAudit = file_get_contents(resource_path('js/Pages/AmoAccounts/CrmAudit/Index.tsx'));
        $crmFields = file_get_contents(resource_path('js/Pages/AmoAccounts/CrmAudit/Fields.tsx'));
        $events = file_get_contents(resource_path('js/Pages/AmoAccounts/EventsSync/Index.tsx'));
        $tasks = file_get_contents(resource_path('js/Pages/AmoAccounts/TaskStatistics/Index.tsx'));

        $this->assertStringContainsString('shadow-theme-sm', $crmAudit);
        $this->assertStringContainsString('rounded-2xl border border-gray-200 bg-white', $crmFields);
        $this->assertStringContainsString('shadow-theme-sm', $events);
        $this->assertStringContainsString('shadow-theme-sm', $tasks);
        $this->assertStringContainsString('focus:border-brand-300 focus:ring-brand-500/10', $crmAudit.$events.$tasks);
    }

    public function test_module_admin_pages_use_tailadmin_surfaces(): void
    {
        $catalogs = file_get_contents(resource_path('js/Pages/AmoAccounts/Catalogs/Index.tsx'));
        $integrations = file_get_contents(resource_path('js/Pages/AmoAccounts/Integrations.tsx'));
        $widgets = file_get_contents(resource_path('js/Pages/AmoAccounts/Widgets.tsx'));
        $settings = file_get_contents(resource_path('js/Pages/AmoAccounts/Widgets/Settings.tsx'));

        $this->assertStringContainsString('shadow-theme-sm', $catalogs);
        $this->assertStringContainsString('shadow-theme-sm', $integrations);
        $this->assertStringContainsString('shadow-theme-sm', $widgets);
        $this->assertStringContainsString('shadow-theme-sm', $settings);
        $this->assertStringContainsString('focus:border-brand-300 focus:ring-brand-500/10', $catalogs.$settings);
    }

    public function test_support_pages_use_tailadmin_surfaces(): void
    {
        $responsibility = file_get_contents(resource_path('js/Pages/AmoAccounts/ResponsibilityRedistribution/Index.tsx'));
        $login = file_get_contents(resource_path('js/Pages/Auth/Login.tsx'));
        $register = file_get_contents(resource_path('js/Pages/Auth/Register.tsx'));
        $oauthIndex = file_get_contents(resource_path('js/Pages/OAuth/External/Index.tsx'));
        $oauthShow = file_get_contents(resource_path('js/Pages/OAuth/External/Show.tsx'));

        $this->assertStringContainsString('shadow-theme-sm', $responsibility);
        $this->assertStringContainsString('shadow-theme-sm', $login);
        $this->assertStringContainsString('shadow-theme-sm', $register);
        $this->assertStringContainsString('shadow-theme-sm', $oauthIndex);
        $this->assertStringContainsString('shadow-theme-sm', $oauthShow);
    }

    public function test_task_statistics_command_queues_sync_without_duplicate_fresh_run(): void
    {
        Queue::fake();

        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $this->artisan('amo:sync-task-statistics', [
            'accountId' => $account->id,
            '--from' => '2026-01-01',
            '--to' => '2026-06-10',
        ])->assertExitCode(0);

        Queue::assertPushed(SyncAmoTaskStatisticsJob::class, 1);
        $this->assertDatabaseHas('task_statistics_sync_runs', [
            'amo_account_id' => $account->id,
            'status' => TaskStatisticsSyncRun::STATUS_PENDING,
            'period_from' => '2026-04-27 00:00:00',
            'period_to' => '2026-06-10 23:59:59',
        ]);

        $this->artisan('amo:sync-task-statistics', [
            'accountId' => $account->id,
            '--from' => '2026-01-01',
            '--to' => '2026-06-10',
        ])->assertExitCode(0);

        Queue::assertPushed(SyncAmoTaskStatisticsJob::class, 1);
        $this->assertSame(1, TaskStatisticsSyncRun::query()->where('amo_account_id', $account->id)->count());
    }

    public function test_task_statistics_command_uses_incremental_cursor_and_refresh_mode(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));

        try {
            $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

            $this->artisan('amo:sync-task-statistics', ['accountId' => $account->id])->assertExitCode(0);

            $firstRun = TaskStatisticsSyncRun::query()->firstOrFail();
            $this->assertSame('2026-04-27 00:00:00', $firstRun->period_from->format('Y-m-d H:i:s'));
            $this->assertSame('2026-06-10 23:59:59', $firstRun->period_to->format('Y-m-d H:i:s'));

            $firstRun->forceFill(['status' => TaskStatisticsSyncRun::STATUS_COMPLETED, 'created_at' => now()->subHours(3)])->save();
            $account->markTaskStatisticsSyncedUntil(Carbon::parse('2026-06-10 10:00:00'));

            $this->artisan('amo:sync-task-statistics', ['accountId' => $account->id])->assertExitCode(0);

            $incrementalRun = TaskStatisticsSyncRun::query()->latest('id')->firstOrFail();
            $this->assertSame('2026-06-10 08:00:00', $incrementalRun->period_from->format('Y-m-d H:i:s'));
            $this->assertSame('2026-06-10 23:59:59', $incrementalRun->period_to->format('Y-m-d H:i:s'));

            $incrementalRun->forceFill(['status' => TaskStatisticsSyncRun::STATUS_COMPLETED, 'created_at' => now()->subHours(3)])->save();
            $this->artisan('amo:sync-task-statistics', [
                'accountId' => $account->id,
                '--mode' => 'refresh',
                '--days' => 7,
            ])->assertExitCode(0);

            $refreshRun = TaskStatisticsSyncRun::query()->latest('id')->firstOrFail();
            $this->assertSame('2026-06-04 00:00:00', $refreshRun->period_from->format('Y-m-d H:i:s'));
            $this->assertSame('2026-06-10 23:59:59', $refreshRun->period_to->format('Y-m-d H:i:s'));

            $refreshRun->forceFill(['status' => TaskStatisticsSyncRun::STATUS_COMPLETED, 'created_at' => now()->subHours(3)])->save();
            $this->artisan('amo:sync-task-statistics', [
                'accountId' => $account->id,
                '--mode' => 'refresh',
                '--days' => 45,
            ])->assertExitCode(0);

            $fullRefreshRun = TaskStatisticsSyncRun::query()->latest('id')->firstOrFail();
            $this->assertSame('2026-04-27 00:00:00', $fullRefreshRun->period_from->format('Y-m-d H:i:s'));
            $this->assertSame('2026-06-10 23:59:59', $fullRefreshRun->period_to->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_api_logs_page_renders_inertia_and_hides_secret_headers(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        ApiRequestLog::query()->create([
            'amo_account_id' => $account->id,
            'method' => 'GET',
            'url' => 'https://client.amocrm.ru/api/v4/account',
            'status_code' => 200,
            'duration_ms' => 123,
            'request_payload' => ['Authorization' => '[redacted]'],
            'response_payload' => ['id' => 123],
        ]);

        $this->actingAs($viewer)
            ->get('/logs/api')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Logs/Api')
                ->where('logs.data.0.account_name', 'Client')
                ->where('logs.data.0.method', 'GET')
                ->where('logs.data.0.response_payload.id', 123)
                ->has('links.export'))
            ->assertDontSee('Authorization');
    }

    public function test_admin_can_manage_catalogs_and_chained_lists(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $capturedField = null;
        $capturedEnumField = null;

        $catalogsService = Mockery::mock(AmoCatalogsService::class);
        $catalogsService->shouldReceive('fetchCatalogs')
            ->once()
            ->andReturn([
                ['id' => 1001, 'name' => 'Проекты', 'type' => 'regular', 'sort' => 10, 'can_add_elements' => true],
                ['id' => 1002, 'name' => 'Вакансии', 'type' => 'regular', 'sort' => 20, 'can_add_elements' => true],
            ]);
        $catalogsService->shouldReceive('fetchEnumCustomFields')
            ->once()
            ->andReturn([
                [
                    'id' => 4001,
                    'name' => 'Источник',
                    'type' => 'select',
                    'entity_type' => 'leads',
                    'enums' => [
                        ['id' => 10, 'value' => 'Авито', 'sort' => 0],
                        ['id' => 11, 'value' => 'Сайт', 'sort' => 1],
                    ],
                ],
            ]);
        $catalogsService->shouldReceive('createCatalog')
            ->once()
            ->with(Mockery::on(fn (AmoAccount $routeAccount): bool => $routeAccount->is($account)), Mockery::on(fn (array $data): bool => $data['name'] === 'Объекты'))
            ->andReturn([]);
        $catalogsService->shouldReceive('createElements')
            ->once()
            ->with(Mockery::on(fn (AmoAccount $routeAccount): bool => $routeAccount->is($account)), 1001, ['Проект А', 'Проект Б'])
            ->andReturn([]);
        $catalogsService->shouldReceive('createChainedListField')
            ->once()
            ->with(Mockery::on(fn (AmoAccount $routeAccount): bool => $routeAccount->is($account)), Mockery::on(function (array $data) use (&$capturedField): bool {
                $capturedField = $data;

                return true;
            }))
            ->andReturn([]);
        $catalogsService->shouldReceive('updateEnumCustomField')
            ->once()
            ->with(Mockery::on(fn (AmoAccount $routeAccount): bool => $routeAccount->is($account)), 'leads', 4001, Mockery::on(function (array $data) use (&$capturedEnumField): bool {
                $capturedEnumField = $data;

                return true;
            }))
            ->andReturn([]);
        $this->app->instance(AmoCatalogsService::class, $catalogsService);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/catalogs")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Catalogs/Index')
                ->where('catalogs.0.name', 'Проекты')
                ->where('enumFields.0.name', 'Источник')
                ->has('links.store_chained_list_field'));

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/catalogs", ['name' => 'Объекты', 'sort' => 30])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/catalogs/elements", ['catalog_id' => 1001, 'elements' => "Проект А\nПроект Б"])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/catalogs/chained-list-fields", [
                'name' => 'Проект / Вакансия',
                'entity_type' => 'leads',
                'sort' => 100,
                'levels' => [
                    ['title' => 'Проект', 'catalog_id' => 1001],
                    ['title' => 'Вакансия', 'catalog_id' => 1002],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(0, $capturedField['levels'][0]['parent_catalog_id']);
        $this->assertSame(1001, $capturedField['levels'][1]['parent_catalog_id']);

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/catalogs/enum-fields", [
                'entity_type' => 'leads',
                'field_id' => 4001,
                'name' => 'Источник',
                'values' => "10|Авито\n11|Сайт\nTelegram",
            ])
            ->assertRedirect();

        $this->assertSame('Источник', $capturedEnumField['name']);
        $this->assertSame(['id' => 10, 'value' => 'Авито'], $capturedEnumField['enums'][0]);
        $this->assertSame(['value' => 'Telegram'], $capturedEnumField['enums'][2]);

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/catalogs", ['name' => 'Forbidden'])
            ->assertForbidden();
    }

    public function test_leads_page_filters_and_exports_current_filter(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        CrmPipelineSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'name' => 'Sales',
            'sort' => 10,
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmPipelineStatusSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'amo_status_id' => 20,
            'name' => 'New',
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmEntitySnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'external_id' => '100',
            'name' => 'Visible Lead',
            'pipeline_id' => 10,
            'status_id' => 20,
            'responsible_user_id' => 55,
            'entity_created_at' => now()->subDay(),
            'entity_updated_at' => now(),
            'custom_fields_values' => [['field_name' => 'Project', 'values' => [['value' => 'A']]]],
            'embedded' => [],
            'raw' => ['id' => 100, 'price' => 5000],
            'synced_at' => now(),
        ]);
        AmoUsersSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_user_id' => 55,
            'name' => 'Sales Manager',
            'email' => 'manager@example.test',
            'rights' => [],
            'is_admin' => false,
            'is_active' => true,
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmEntitySnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'external_id' => '200',
            'name' => 'Hidden Lead',
            'pipeline_id' => 99,
            'status_id' => 88,
            'entity_created_at' => now()->subDay(),
            'entity_updated_at' => now(),
            'embedded' => [],
            'raw' => ['id' => 200],
            'synced_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get("/amo-accounts/{$account->id}/leads?pipeline_id=10")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Leads')
                ->where('account.name', 'Client')
                ->where('filters.pipeline_id', '10')
                ->where('leads.data.0.name', 'Visible Lead')
                ->where('leads.data.0.pipeline_name', 'Sales')
                ->where('leads.data.0.status_name', 'New')
                ->where('leads.data.0.responsible_name', 'Sales Manager')
                ->where('leads.data.0.price', 5000)
                ->missing('leads.data.1'));

        $response = $this->actingAs($viewer)
            ->get("/amo-accounts/{$account->id}/leads-export?pipeline_id=10");

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Visible Lead', $content);
        $this->assertStringContainsString('Sales Manager (55)', $content);
        $this->assertStringContainsString('5000', $content);
        $this->assertStringNotContainsString('Hidden Lead', $content);
    }

    public function test_admin_can_clone_pipeline_and_viewer_cannot_clone_pipeline(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $pipelinesService = Mockery::mock(AmoPipelinesService::class);
        $pipelinesService->shouldReceive('fetchPipelineDetails')
            ->once()
            ->with(Mockery::type(AmoAccount::class), 10)
            ->andReturn([
                'pipeline' => ['id' => 10, 'name' => 'Sales'],
                'statuses' => [['id' => 20, 'name' => 'New', 'sort' => 10, 'color' => '#99ccff']],
            ]);
        $pipelinesService->shouldReceive('clonePipeline')
            ->once()
            ->with(Mockery::type(AmoAccount::class), 10, 'Sales Copy')
            ->andReturn(['_embedded' => ['pipelines' => [['id' => 123]]]]);
        $this->app->instance(AmoPipelinesService::class, $pipelinesService);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/pipelines/10/clone")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Pipelines/Clone')
                ->where('pipeline.name', 'Sales')
                ->where('statuses.0.name', 'New')
                ->has('links.submit'));

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/pipelines/10/clone", ['name' => 'Sales Copy'])
            ->assertRedirect(route('amo-accounts.pipelines.index', $account));

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/pipelines/10/clone", ['name' => 'Viewer Copy'])
            ->assertForbidden();
    }

    public function test_clone_pipeline_failure_returns_form_error(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $pipelinesService = Mockery::mock(AmoPipelinesService::class);
        $pipelinesService->shouldReceive('clonePipeline')
            ->once()
            ->with(Mockery::type(AmoAccount::class), 10, 'Sales Copy')
            ->andThrow(new \RuntimeException('amoCRM API error.'));
        $this->app->instance(AmoPipelinesService::class, $pipelinesService);

        $this->actingAs($admin)
            ->from("/amo-accounts/{$account->id}/pipelines/10/clone")
            ->post("/amo-accounts/{$account->id}/pipelines/10/clone", ['name' => 'Sales Copy'])
            ->assertRedirect("/amo-accounts/{$account->id}/pipelines/10/clone")
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_open_crm_audit_and_viewer_cannot_run_it(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        Queue::fake();
        CrmPipelineSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'name' => 'Sales',
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/crm-audit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/CrmAudit/Index')
                ->where('account.name', 'Client')
                ->where('can.sync', true)
                ->where('pipelines.0.name', 'Sales')
                ->has('summary')
                ->has('links.sync'));

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/crm-audit/sync", [
                'from' => '2026-01-01',
                'to' => '2026-05-05',
                'pipeline_id' => 10,
                'ignore_period' => '1',
            ])
            ->assertRedirect();

        Queue::assertPushed(SyncCrmAuditJob::class, fn (SyncCrmAuditJob $job): bool =>
            $job->amoAccountId === $account->id
            && $job->from === null
            && $job->to === null
            && $job->pipelineId === 10
        );

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/crm-audit/sync", [
                'from' => '2026-01-01',
                'to' => '2026-05-05',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_manage_lead_sync_schedules_for_account_pipeline(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        CrmPipelineSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'name' => 'Sales',
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmPipelineSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 20,
            'name' => 'Hiring',
            'sort' => 20,
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/lead-sync-schedules")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/LeadSyncSchedules/Index')
                ->where('account.name', 'Client')
                ->where('can.manage', true)
                ->where('pipelines.0.name', 'Sales')
                ->has('intervals')
                ->has('links.store'));

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/lead-sync-schedules", [
                'amo_pipeline_id' => 10,
                'interval_minutes' => 60,
                'lookback_days' => 45,
                'is_enabled' => '1',
            ])
            ->assertRedirect();

        $schedule = LeadSyncSchedule::query()->firstOrFail();
        $this->assertSame('Sales', $schedule->pipeline_name);
        $this->assertTrue($schedule->is_enabled);
        $this->assertNotNull($schedule->next_run_at);

        $this->actingAs($admin)
            ->put("/amo-accounts/{$account->id}/lead-sync-schedules/{$schedule->id}", [
                'amo_pipeline_id' => 20,
                'interval_minutes' => 180,
                'lookback_days' => 30,
            ])
            ->assertRedirect();

        $schedule->refresh();
        $this->assertSame(20, $schedule->amo_pipeline_id);
        $this->assertSame('Hiring', $schedule->pipeline_name);
        $this->assertSame(180, $schedule->interval_minutes);
        $this->assertFalse($schedule->is_enabled);

        $this->actingAs($admin)
            ->delete("/amo-accounts/{$account->id}/lead-sync-schedules/{$schedule->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('lead_sync_schedules', ['id' => $schedule->id]);
    }

    public function test_viewer_cannot_manage_lead_sync_schedules(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        CrmPipelineSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'name' => 'Sales',
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get("/amo-accounts/{$account->id}/lead-sync-schedules")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/LeadSyncSchedules/Index')
                ->where('can.manage', false));

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/lead-sync-schedules", [
                'amo_pipeline_id' => 10,
                'interval_minutes' => 60,
                'lookback_days' => 45,
            ])
            ->assertForbidden();
    }

    public function test_due_lead_sync_schedule_runs_only_configured_pipeline(): void
    {
        Carbon::setTestNow('2026-06-11 12:00:00');

        $account = AmoAccount::query()->create([
            'name' => 'Client',
            'base_domain' => 'client.amocrm.ru',
            'is_active' => true,
        ]);
        $inactiveAccount = AmoAccount::query()->create([
            'name' => 'Inactive',
            'base_domain' => 'inactive.amocrm.ru',
            'is_active' => false,
        ]);
        $due = LeadSyncSchedule::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'pipeline_name' => 'Sales',
            'interval_minutes' => 60,
            'lookback_days' => 45,
            'is_enabled' => true,
            'next_run_at' => now()->subMinute(),
        ]);
        LeadSyncSchedule::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 20,
            'pipeline_name' => 'Hiring',
            'interval_minutes' => 60,
            'lookback_days' => 45,
            'is_enabled' => false,
            'next_run_at' => now()->subMinute(),
        ]);
        LeadSyncSchedule::query()->create([
            'amo_account_id' => $inactiveAccount->id,
            'amo_pipeline_id' => 30,
            'pipeline_name' => 'Inactive',
            'interval_minutes' => 60,
            'lookback_days' => 45,
            'is_enabled' => true,
            'next_run_at' => now()->subMinute(),
        ]);

        $auditService = Mockery::mock(CrmAuditService::class);
        $auditService->shouldReceive('syncOperationalData')
            ->once()
            ->withArgs(fn (AmoAccount $passedAccount, Carbon $from, Carbon $to, int $pipelineId): bool =>
                $passedAccount->id === $account->id
                && $from->toDateTimeString() === '2026-04-27 00:00:00'
                && $to->toDateTimeString() === '2026-06-11 23:59:59'
                && $pipelineId === 10
            )
            ->andReturn(['leads' => 37]);
        $this->app->instance(CrmAuditService::class, $auditService);

        $this->artisan('amo:run-lead-sync-schedules')->assertExitCode(0);

        $due->refresh();
        $this->assertSame(LeadSyncSchedule::STATUS_COMPLETED, $due->last_status);
        $this->assertSame(37, $due->last_synced_count);
        $this->assertSame('2026-06-11 13:00:00', $due->next_run_at?->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_admin_can_run_one_time_lead_sync_without_changing_schedule_window(): void
    {
        Carbon::setTestNow('2026-06-11 12:00:00');

        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $schedule = LeadSyncSchedule::query()->create([
            'amo_account_id' => $account->id,
            'amo_pipeline_id' => 10,
            'pipeline_name' => 'Sales',
            'interval_minutes' => 360,
            'lookback_days' => 2,
            'is_enabled' => true,
            'next_run_at' => now()->addHours(6),
        ]);

        $auditService = Mockery::mock(CrmAuditService::class);
        $auditService->shouldReceive('syncOperationalData')
            ->once()
            ->withArgs(fn (AmoAccount $passedAccount, Carbon $from, Carbon $to, int $pipelineId): bool =>
                $passedAccount->id === $account->id
                && $from->toDateTimeString() === '2026-04-27 00:00:00'
                && $to->toDateTimeString() === '2026-06-11 23:59:59'
                && $pipelineId === 10
            )
            ->andReturn(['leads' => 250]);
        $this->app->instance(CrmAuditService::class, $auditService);

        $this->actingAs($admin)
            ->post("/amo-accounts/{$account->id}/lead-sync-schedules/{$schedule->id}/run", [
                'lookback_days' => 45,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Разовая синхронизация завершена. Загружено сделок: 250.');

        $schedule->refresh();
        $this->assertSame(2, $schedule->lookback_days);
        $this->assertSame('2026-06-11 18:00:00', $schedule->next_run_at?->toDateTimeString());
        $this->assertSame(LeadSyncSchedule::STATUS_COMPLETED, $schedule->last_status);
        $this->assertSame(250, $schedule->last_synced_count);

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/lead-sync-schedules/{$schedule->id}/run", [
                'lookback_days' => 45,
            ])
            ->assertForbidden();

        Carbon::setTestNow();
    }

    public function test_scheduler_uses_only_configured_lead_sync_schedules_for_automatic_data_sync(): void
    {
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));
        $syncPage = file_get_contents(resource_path('js/Pages/AmoAccounts/LeadSyncSchedules/Index.tsx'));

        $this->assertStringContainsString('amo:run-lead-sync-schedules', $consoleRoutes);
        $this->assertStringNotContainsString('amo:sync-task-statistics', $consoleRoutes);
        $this->assertStringNotContainsString('SyncAmoUsersAndRolesJob::dispatch', $consoleRoutes);
        $this->assertStringContainsString('Разовая загрузка', $syncPage);
    }

    public function test_admin_can_view_lead_and_contact_field_ids(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        CrmCustomFieldSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'amo_field_id' => 100,
            'name' => 'Рекрутер',
            'field_type' => 'select',
            'sort' => 10,
            'enums' => [['id' => 1, 'value' => 'Иван']],
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmCustomFieldSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'contacts',
            'amo_field_id' => 200,
            'name' => 'Telegram',
            'field_type' => 'text',
            'sort' => 20,
            'raw' => [],
            'synced_at' => now(),
        ]);
        CrmCustomFieldSnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'companies',
            'amo_field_id' => 300,
            'name' => 'ИНН',
            'field_type' => 'text',
            'sort' => 30,
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/crm-audit/fields")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/CrmAudit/Fields')
                ->where('summary.leads', 1)
                ->where('summary.contacts', 1)
                ->where('fields.0.amo_field_id', 100)
                ->where('fields.0.name', 'Рекрутер')
                ->where('fields.0.enums_count', 1)
                ->where('fields.1.amo_field_id', 200)
                ->missing('fields.2'));

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/crm-audit/fields?entity_type=contacts&search=Telegram")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.entity_type', 'contacts')
                ->where('filters.search', 'Telegram')
                ->where('fields.0.amo_field_id', 200)
                ->missing('fields.1'));
    }

    public function test_public_install_page_creates_pending_oauth_connection(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('OAuth/Public/Install')
                ->where('external.name', 'Sonic Expert')
                ->has('connection.state'));

        $this->assertDatabaseHas('amo_oauth_connections', [
            'owner_user_id' => null,
            'status' => AmoOAuthConnection::STATUS_PENDING,
        ]);
    }

    public function test_external_oauth_secrets_and_callback_create_oauth_account(): void
    {
        $admin = User::factory()->admin()->create();
        $connection = AmoOAuthConnection::query()->create([
            'owner_user_id' => $admin->id,
            'state' => 'state-token',
            'name' => 'OAuth Client',
            'redirect_uri' => 'https://app.example.test/amo-oauth/callback',
            'secrets_uri' => 'https://app.example.test/amo-oauth/external/secrets',
            'scopes' => ['crm', 'notifications'],
            'expires_at' => now()->addMinutes(30),
        ]);

        $exchanger = Mockery::mock(AmoOAuthTokenExchanger::class);
        $exchanger->shouldReceive('exchangeCode')
            ->once()
            ->with('client.amocrm.ru', 'client-id', 'client-secret', $connection->redirect_uri, 'auth-code')
            ->andReturn(new AccessToken([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires' => now()->addHour()->timestamp,
            ]));
        $this->app->instance(AmoOAuthTokenExchanger::class, $exchanger);

        $http = Mockery::mock(AmoFallbackHttpClient::class);
        $http->shouldReceive('get')
            ->once()
            ->with(Mockery::type(AmoAccount::class), '/api/v4/account')
            ->andReturn([
                'id' => 12345,
                'name' => 'Client Company',
                'subdomain' => 'client',
                'timezone' => 'Europe/Moscow',
                'currency' => 'RUB',
                'country' => 'RU',
            ]);
        $this->app->instance(AmoFallbackHttpClient::class, $http);

        $usersService = Mockery::mock(AmoUsersService::class);
        $usersService->shouldReceive('syncUsersAndRoles')
            ->once()
            ->with(Mockery::type(AmoAccount::class));
        $this->app->instance(AmoUsersService::class, $usersService);

        $this->postJson('/amo-oauth/external/secrets', [
            'state' => 'state-token',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
        ])->assertOk();

        $this->get('/amo-oauth/callback?state=state-token&code=auth-code&referer=client.amocrm.ru')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('OAuth/Public/Callback')
                ->where('connection.status', AmoOAuthConnection::STATUS_CONNECTED)
                ->where('connection.account.name', 'Client Company'));

        $account = AmoAccount::query()->where('base_domain', 'client.amocrm.ru')->firstOrFail();
        $credential = $account->credentials()->firstOrFail();

        $this->assertSame('Client Company', $account->name);
        $this->assertSame(12345, $account->account_id);
        $this->assertSame('Europe/Moscow', $account->settings['timezone']);
        $this->assertSame('RUB', $account->settings['currency']);
        $this->assertSame(AmoCredential::AUTH_OAUTH, $credential->auth_type);
        $this->assertSame('access-token', $credential->access_token);
        $this->assertSame('refresh-token', $credential->refresh_token);
        $this->assertSame('client-id', $credential->client_id);
        $this->assertSame('client-secret', $credential->client_secret);
        $this->assertDatabaseHas('amo_oauth_connections', [
            'id' => $connection->id,
            'amo_account_id' => $account->id,
            'status' => AmoOAuthConnection::STATUS_CONNECTED,
        ]);
    }

    public function test_admin_oauth_pages_render_inertia_without_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $connection = AmoOAuthConnection::query()->create([
            'owner_user_id' => $admin->id,
            'amo_account_id' => $account->id,
            'state' => 'state-token',
            'name' => 'OAuth Client',
            'base_domain' => 'client.amocrm.ru',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_uri' => 'https://app.example.test/amo-oauth/callback',
            'secrets_uri' => 'https://app.example.test/amo-oauth/external/secrets',
            'scopes' => ['crm'],
            'status' => AmoOAuthConnection::STATUS_CONNECTED,
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($admin)
            ->get('/amo-oauth/external')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('OAuth/External/Index')
                ->where('connections.data.0.name', 'OAuth Client')
                ->where('connections.data.0.account.name', 'Client'));

        $this->actingAs($admin)
            ->get("/amo-oauth/external/{$connection->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('OAuth/External/Show')
                ->where('connection.name', 'OAuth Client')
                ->where('connection.state', 'state-token')
                ->where('connection.account.name', 'Client'))
            ->assertDontSee('client-secret')
            ->assertDontSee('client-id');
    }
}
