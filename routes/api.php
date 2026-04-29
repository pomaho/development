<?php

use App\Http\Controllers\Api\Internal\AmoAccountApiController;
use App\Http\Controllers\Api\Internal\DashboardApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('internal')->group(function (): void {
    Route::get('/amo-accounts', [AmoAccountApiController::class, 'index']);
    Route::get('/amo-accounts/{amo_account}/users', [AmoAccountApiController::class, 'users']);
    Route::get('/amo-accounts/{amo_account}/roles', [AmoAccountApiController::class, 'roles']);
    Route::post('/amo-accounts/{amo_account}/sync-users', [AmoAccountApiController::class, 'syncUsers']);
    Route::post('/amo-accounts/{amo_account}/test-connection', [AmoAccountApiController::class, 'testConnection']);
    Route::get('/dashboard/summary', [DashboardApiController::class, 'summary']);
});
