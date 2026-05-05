<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Api\Internal\AmoAccountApiController;
use App\Http\Controllers\Api\Internal\DashboardApiController;
use App\Http\Controllers\Web\AmoAccountController;
use App\Http\Controllers\Web\AmoAccountIntegrationsController;
use App\Http\Controllers\Web\AmoAccountWidgetsController;
use App\Http\Controllers\Web\AmoExternalOAuthController;
use App\Http\Controllers\Web\AmoPipelinesController;
use App\Http\Controllers\Web\AmoRolesController;
use App\Http\Controllers\Web\AmoUsersController;
use App\Http\Controllers\Web\ApiLogController;
use App\Http\Controllers\Web\CrmAuditController;
use App\Http\Controllers\Web\DashboardController;
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

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/amo-oauth/external', [AmoExternalOAuthController::class, 'index'])->name('amo-oauth.external.index');
    Route::post('/amo-oauth/external', [AmoExternalOAuthController::class, 'store'])->name('amo-oauth.external.store');
    Route::get('/amo-oauth/external/{connection}', [AmoExternalOAuthController::class, 'show'])->name('amo-oauth.external.show');
    Route::resource('amo-accounts', AmoAccountController::class);
    Route::post('/amo-accounts/{amo_account}/test', [AmoAccountController::class, 'test'])->name('amo-accounts.test');
    Route::post('/amo-accounts/{amo_account}/sync', [AmoAccountController::class, 'sync'])->name('amo-accounts.sync');
    Route::post('/amo-accounts/{amo_account}/deactivate', [AmoAccountController::class, 'deactivate'])->name('amo-accounts.deactivate');
    Route::get('/amo-accounts/{amo_account}/dashboard', DashboardController::class)->name('amo-accounts.dashboard');
    Route::get('/amo-accounts/{amo_account}/integrations', AmoAccountIntegrationsController::class)->name('amo-accounts.integrations');
    Route::get('/amo-accounts/{amo_account}/widgets', AmoAccountWidgetsController::class)->name('amo-accounts.widgets');
    Route::get('/amo-accounts/{amo_account}/pipelines', [AmoPipelinesController::class, 'index'])->name('amo-accounts.pipelines.index');
    Route::get('/amo-accounts/{amo_account}/pipelines/create', [AmoPipelinesController::class, 'create'])->name('amo-accounts.pipelines.create');
    Route::post('/amo-accounts/{amo_account}/pipelines', [AmoPipelinesController::class, 'store'])->name('amo-accounts.pipelines.store');
    Route::get('/amo-accounts/{amo_account}/crm-audit', [CrmAuditController::class, 'index'])->name('amo-accounts.crm-audit.index');
    Route::post('/amo-accounts/{amo_account}/crm-audit/sync', [CrmAuditController::class, 'sync'])->name('amo-accounts.crm-audit.sync');
    Route::get('/amo-accounts/{amo_account}/users', AmoUsersController::class)->name('amo-accounts.users');
    Route::get('/amo-accounts/{amo_account}/roles', AmoRolesController::class)->name('amo-accounts.roles');
    Route::get('/logs/api', ApiLogController::class)->name('logs.api');

    Route::prefix('/api/internal')->group(function (): void {
        Route::get('/amo-accounts', [AmoAccountApiController::class, 'index']);
        Route::get('/amo-accounts/{amo_account}/users', [AmoAccountApiController::class, 'users']);
        Route::get('/amo-accounts/{amo_account}/roles', [AmoAccountApiController::class, 'roles']);
        Route::post('/amo-accounts/{amo_account}/sync-users', [AmoAccountApiController::class, 'syncUsers']);
        Route::post('/amo-accounts/{amo_account}/test-connection', [AmoAccountApiController::class, 'testConnection']);
        Route::get('/dashboard/summary', [DashboardApiController::class, 'summary']);
    });
});
