<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Api\Internal\AmoAccountApiController;
use App\Http\Controllers\Api\Internal\DashboardApiController;
use App\Http\Controllers\Web\AmoAccountController;
use App\Http\Controllers\Web\AmoAccountIntegrationsController;
use App\Http\Controllers\Web\AmoAccountWidgetsController;
use App\Http\Controllers\Web\AmoCatalogsController;
use App\Http\Controllers\Web\AmoExternalOAuthController;
use App\Http\Controllers\Web\AmoLeadTransferController;
use App\Http\Controllers\Web\AmoLeadsController;
use App\Http\Controllers\Web\AmoPipelinesController;
use App\Http\Controllers\Web\AmoResponsibilityRedistributionController;
use App\Http\Controllers\Web\AmoRolesController;
use App\Http\Controllers\Web\AmoTaskStatisticsController;
use App\Http\Controllers\Web\AmoUsersController;
use App\Http\Controllers\Web\ApiLogController;
use App\Http\Controllers\Web\CrmAuditController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LeadSyncScheduleController;
use App\Http\Controllers\Widget\AmoTaskOverdueDashboardController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::post('/amo-oauth/external/secrets', [AmoExternalOAuthController::class, 'secrets'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('amo-oauth.external.secrets');
Route::get('/amo-oauth/callback', [AmoExternalOAuthController::class, 'callback'])->name('amo-oauth.callback');
Route::get('/install', [AmoExternalOAuthController::class, 'install'])->name('amo-oauth.install');
Route::get('/widgets/amo/{publicKey}/task-overdue-dashboard', [AmoTaskOverdueDashboardController::class, 'show'])
    ->middleware('amo-widget-frame-policy')
    ->name('widgets.amo.task-overdue-dashboard.show');
Route::get('/api/widgets/amo/{publicKey}/task-overdue-dashboard', [AmoTaskOverdueDashboardController::class, 'json'])
    ->middleware('amo-widget-frame-policy')
    ->name('api.widgets.amo.task-overdue-dashboard.show');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/amo-oauth/external', [AmoExternalOAuthController::class, 'index'])->name('amo-oauth.external.index');
    Route::get('/amo-oauth/external/{connection}', [AmoExternalOAuthController::class, 'show'])->name('amo-oauth.external.show');
    Route::resource('amo-accounts', AmoAccountController::class)->except(['create', 'store']);
    Route::get('/amo-accounts-export', [AmoAccountController::class, 'export'])->name('amo-accounts.export');
    Route::post('/amo-accounts/{amo_account}/test', [AmoAccountController::class, 'test'])->name('amo-accounts.test');
    Route::post('/amo-accounts/{amo_account}/sync', [AmoAccountController::class, 'sync'])->name('amo-accounts.sync');
    Route::post('/amo-accounts/{amo_account}/deactivate', [AmoAccountController::class, 'deactivate'])->name('amo-accounts.deactivate');
    Route::get('/amo-accounts/{amo_account}/dashboard', DashboardController::class)->name('amo-accounts.dashboard');
    Route::get('/amo-accounts/{amo_account}/integrations', AmoAccountIntegrationsController::class)->name('amo-accounts.integrations');
    Route::get('/amo-accounts/{amo_account}/widgets', AmoAccountWidgetsController::class)->name('amo-accounts.widgets');
    Route::get('/amo-accounts/{amo_account}/widgets/{dashboard_widget}/settings', [AmoAccountWidgetsController::class, 'settings'])->name('amo-accounts.widgets.settings');
    Route::post('/amo-accounts/{amo_account}/widgets/{dashboard_widget}/settings', [AmoAccountWidgetsController::class, 'updateSettings'])->name('amo-accounts.widgets.settings.update');
    Route::get('/amo-accounts/{amo_account}/catalogs', [AmoCatalogsController::class, 'index'])->name('amo-accounts.catalogs.index');
    Route::post('/amo-accounts/{amo_account}/catalogs', [AmoCatalogsController::class, 'storeCatalog'])->name('amo-accounts.catalogs.store');
    Route::post('/amo-accounts/{amo_account}/catalogs/elements', [AmoCatalogsController::class, 'storeElements'])->name('amo-accounts.catalogs.elements.store');
    Route::post('/amo-accounts/{amo_account}/catalogs/chained-list-fields', [AmoCatalogsController::class, 'storeChainedListField'])->name('amo-accounts.catalogs.chained-list-fields.store');
    Route::post('/amo-accounts/{amo_account}/catalogs/enum-fields', [AmoCatalogsController::class, 'updateEnumField'])->name('amo-accounts.catalogs.enum-fields.update');
    Route::get('/amo-accounts/{amo_account}/pipelines', [AmoPipelinesController::class, 'index'])->name('amo-accounts.pipelines.index');
    Route::get('/amo-accounts/{amo_account}/pipelines-export', [AmoPipelinesController::class, 'export'])->name('amo-accounts.pipelines.export');
    Route::get('/amo-accounts/{amo_account}/pipelines/create', [AmoPipelinesController::class, 'create'])->name('amo-accounts.pipelines.create');
    Route::get('/amo-accounts/{amo_account}/pipelines/transfer-leads', [AmoLeadTransferController::class, 'index'])->name('amo-accounts.pipelines.transfer-leads');
    Route::post('/amo-accounts/{amo_account}/pipelines/transfer-leads', [AmoLeadTransferController::class, 'store'])->name('amo-accounts.pipelines.transfer-leads.store');
    Route::get('/amo-accounts/{amo_account}/pipelines/{pipelineId}/clone', [AmoPipelinesController::class, 'cloneForm'])->whereNumber('pipelineId')->name('amo-accounts.pipelines.clone-form');
    Route::post('/amo-accounts/{amo_account}/pipelines/{pipelineId}/clone', [AmoPipelinesController::class, 'clone'])->whereNumber('pipelineId')->name('amo-accounts.pipelines.clone');
    Route::get('/amo-accounts/{amo_account}/pipelines/{pipelineId}', [AmoPipelinesController::class, 'show'])->whereNumber('pipelineId')->name('amo-accounts.pipelines.show');
    Route::post('/amo-accounts/{amo_account}/pipelines', [AmoPipelinesController::class, 'store'])->name('amo-accounts.pipelines.store');
    Route::get('/amo-accounts/{amo_account}/responsibility-redistribution', [AmoResponsibilityRedistributionController::class, 'index'])->name('amo-accounts.responsibility-redistribution.index');
    Route::post('/amo-accounts/{amo_account}/responsibility-redistribution/preview', [AmoResponsibilityRedistributionController::class, 'preview'])->name('amo-accounts.responsibility-redistribution.preview');
    Route::post('/amo-accounts/{amo_account}/responsibility-redistribution', [AmoResponsibilityRedistributionController::class, 'store'])->name('amo-accounts.responsibility-redistribution.store');
    Route::get('/amo-accounts/{amo_account}/task-statistics', [AmoTaskStatisticsController::class, 'index'])->name('amo-accounts.task-statistics.index');
    Route::post('/amo-accounts/{amo_account}/task-statistics/sync', [AmoTaskStatisticsController::class, 'sync'])->name('amo-accounts.task-statistics.sync');
    Route::get('/amo-accounts/{amo_account}/task-statistics-export', [AmoTaskStatisticsController::class, 'export'])->name('amo-accounts.task-statistics.export');
    Route::get('/amo-accounts/{amo_account}/events-sync', [AmoTaskStatisticsController::class, 'events'])->name('amo-accounts.events-sync.index');
    Route::post('/amo-accounts/{amo_account}/events-sync', [AmoTaskStatisticsController::class, 'syncEvents'])->name('amo-accounts.events-sync.sync');
    Route::post('/amo-accounts/{amo_account}/events-sync/settings', [AmoTaskStatisticsController::class, 'updateEventSettings'])->name('amo-accounts.events-sync.settings');
    Route::get('/amo-accounts/{amo_account}/crm-audit', [CrmAuditController::class, 'index'])->name('amo-accounts.crm-audit.index');
    Route::get('/amo-accounts/{amo_account}/crm-audit/fields', [CrmAuditController::class, 'fields'])->name('amo-accounts.crm-audit.fields');
    Route::post('/amo-accounts/{amo_account}/crm-audit/sync', [CrmAuditController::class, 'sync'])->name('amo-accounts.crm-audit.sync');
    Route::get('/amo-accounts/{amo_account}/lead-sync-schedules', [LeadSyncScheduleController::class, 'index'])->name('amo-accounts.lead-sync-schedules.index');
    Route::post('/amo-accounts/{amo_account}/lead-sync-schedules', [LeadSyncScheduleController::class, 'store'])->name('amo-accounts.lead-sync-schedules.store');
    Route::put('/amo-accounts/{amo_account}/lead-sync-schedules/{lead_sync_schedule}', [LeadSyncScheduleController::class, 'update'])->name('amo-accounts.lead-sync-schedules.update');
    Route::post('/amo-accounts/{amo_account}/lead-sync-schedules/{lead_sync_schedule}/run', [LeadSyncScheduleController::class, 'run'])->name('amo-accounts.lead-sync-schedules.run');
    Route::delete('/amo-accounts/{amo_account}/lead-sync-schedules/{lead_sync_schedule}', [LeadSyncScheduleController::class, 'destroy'])->name('amo-accounts.lead-sync-schedules.destroy');
    Route::get('/amo-accounts/{amo_account}/users', AmoUsersController::class)->name('amo-accounts.users');
    Route::get('/amo-accounts/{amo_account}/users-export', [AmoUsersController::class, 'export'])->name('amo-accounts.users.export');
    Route::get('/amo-accounts/{amo_account}/leads', AmoLeadsController::class)->name('amo-accounts.leads');
    Route::get('/amo-accounts/{amo_account}/leads-export', [AmoLeadsController::class, 'export'])->name('amo-accounts.leads.export');
    Route::get('/amo-accounts/{amo_account}/roles', AmoRolesController::class)->name('amo-accounts.roles');
    Route::get('/amo-accounts/{amo_account}/roles-export', [AmoRolesController::class, 'export'])->name('amo-accounts.roles.export');
    Route::get('/logs/api', ApiLogController::class)->name('logs.api');
    Route::get('/logs/api-export', [ApiLogController::class, 'export'])->name('logs.api.export');

    Route::prefix('/api/internal')->group(function (): void {
        Route::get('/amo-accounts', [AmoAccountApiController::class, 'index']);
        Route::get('/amo-accounts/{amo_account}/users', [AmoAccountApiController::class, 'users']);
        Route::get('/amo-accounts/{amo_account}/roles', [AmoAccountApiController::class, 'roles']);
        Route::post('/amo-accounts/{amo_account}/sync-users', [AmoAccountApiController::class, 'syncUsers']);
        Route::post('/amo-accounts/{amo_account}/test-connection', [AmoAccountApiController::class, 'testConnection']);
        Route::get('/dashboard/summary', [DashboardApiController::class, 'summary']);
    });
});
