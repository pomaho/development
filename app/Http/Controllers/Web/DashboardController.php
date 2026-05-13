<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Models\ApiRequestLog;
use App\Models\DashboardWidget;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ?AmoAccount $amoAccount = null): Response
    {
        $accountId = $amoAccount?->id ?: ($request->integer('account_id') ?: null);
        $accounts = AmoAccount::query()->orderBy('name')->get();
        $usersQuery = AmoUsersSnapshot::query()->when($accountId, fn ($query) => $query->where('amo_account_id', $accountId));
        $logsQuery = ApiRequestLog::query()
            ->with('account')
            ->whereNotNull('error_message')
            ->when($accountId, fn ($query) => $query->where('amo_account_id', $accountId));

        return Inertia::render('Dashboard/Index', [
            'accounts' => $accounts->map(fn (AmoAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'base_domain' => $account->base_domain,
                'is_active' => $account->is_active,
                'dashboard_url' => route('amo-accounts.dashboard', $account),
            ]),
            'currentAccount' => $amoAccount ? [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
                'is_active' => $amoAccount->is_active,
            ] : null,
            'selectedAccountId' => $accountId,
            'widgets' => DashboardWidget::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
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
                ->get()
                ->map(fn (ApiRequestLog $log): array => [
                    'id' => $log->id,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'account_name' => $log->account?->name,
                    'status_code' => $log->status_code,
                    'error_message' => $log->error_message,
                ]),
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'current_account' => $amoAccount ? [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                ] : null,
            ],
        ]);
    }
}
