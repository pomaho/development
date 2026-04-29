<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Models\ApiRequestLog;
use App\Models\DashboardWidget;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ?AmoAccount $amoAccount = null): View
    {
        $accountId = $amoAccount?->id ?: ($request->integer('account_id') ?: null);
        $accounts = AmoAccount::query()->orderBy('name')->get();
        $usersQuery = AmoUsersSnapshot::query()->when($accountId, fn ($query) => $query->where('amo_account_id', $accountId));
        $logsQuery = ApiRequestLog::query()
            ->with('account')
            ->whereNotNull('error_message')
            ->when($accountId, fn ($query) => $query->where('amo_account_id', $accountId));

        return view('dashboard.index', [
            'accounts' => $accounts,
            'currentAccount' => $amoAccount,
            'selectedAccountId' => $accountId,
            'widgets' => DashboardWidget::query()->where('is_enabled', true)->orderBy('sort_order')->get(),
            'summary' => [
                'accounts_count' => AmoAccount::query()->count(),
                'active_accounts_count' => AmoAccount::query()->where('is_active', true)->count(),
                'last_sync' => $amoAccount?->last_successful_sync_at ?: AmoAccount::query()->max('last_successful_sync_at'),
                'users_count' => (clone $usersQuery)->count(),
                'admins_count' => (clone $usersQuery)->where('is_admin', true)->count(),
            ],
            'recentErrors' => $logsQuery
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
