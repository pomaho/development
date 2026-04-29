<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Models\ApiRequestLog;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'accounts_count' => AmoAccount::query()->count(),
            'active_accounts_count' => AmoAccount::query()->where('is_active', true)->count(),
            'users_count' => AmoUsersSnapshot::query()->count(),
            'admins_count' => AmoUsersSnapshot::query()->where('is_admin', true)->count(),
            'last_sync' => AmoAccount::query()->max('last_successful_sync_at'),
            'recent_api_errors' => ApiRequestLog::query()->whereNotNull('error_message')->latest()->limit(10)->get(),
        ]);
    }
}
