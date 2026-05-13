<?php

namespace Tests\Feature;

use App\Models\AmoAccount;
use App\Models\AmoCredential;
use App\Models\AmoOAuthConnection;
use App\Models\AmoRolesSnapshot;
use App\Models\AmoUsersSnapshot;
use App\Models\ApiRequestLog;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineSnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Models\DashboardWidget;
use App\Models\IntegrationModule;
use App\Models\User;
use App\Services\Amo\AmoFallbackHttpClient;
use App\Services\Amo\AmoOAuthTokenExchanger;
use App\Services\Amo\AmoPipelinesService;
use App\Services\Amo\AmoUsersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/pipelines/create")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/Pipelines/Create')
                ->where('account.name', 'Client')
                ->where('defaultStatuses.0.name', 'Первичный контакт')
                ->has('links.store'));

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
                ->where('widgets.0.component_key', 'metric.users'));
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

    public function test_leads_page_filters_and_exports_current_filter(): void
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

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/crm-audit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AmoAccounts/CrmAudit/Index')
                ->where('account.name', 'Client')
                ->where('can.sync', true)
                ->has('summary')
                ->has('links.sync'));

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/crm-audit/sync", [
                'from' => '2026-01-01',
                'to' => '2026-05-05',
            ])
            ->assertForbidden();
    }

    public function test_public_install_page_creates_pending_oauth_connection(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertSee('Установить интеграцию')
            ->assertSee('Интеграция Sonic Expert');

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
            ->assertSee('Интеграция Sonic Expert установлена');

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
}
