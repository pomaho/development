<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoAccountDashboardWidget;
use App\Models\CrmEntitySnapshot;
use Inertia\Inertia;
use Inertia\Response;

class AmoAnalyticsCenterController extends Controller
{
    public function __invoke(AmoAccount $amoAccount): Response
    {
        $this->authorize('view', $amoAccount);

        return Inertia::render('AmoAccounts/AnalyticsCenter/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'summary' => [
                'leads_count' => CrmEntitySnapshot::query()->where('amo_account_id', $amoAccount->id)->where('entity_type', 'leads')->count(),
                'tasks_count' => CrmEntitySnapshot::query()->where('amo_account_id', $amoAccount->id)->where('entity_type', 'tasks')->count(),
                'events_count' => CrmEntitySnapshot::query()->where('amo_account_id', $amoAccount->id)->where('entity_type', 'events')->count(),
                'dashboard_widgets_count' => AmoAccountDashboardWidget::query()->where('amo_account_id', $amoAccount->id)->where('is_enabled', true)->count(),
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'analytics_center' => route('amo-accounts.analytics.index', $amoAccount),
                    'sync_center' => route('amo-accounts.sync.index', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'catalogs' => route('amo-accounts.catalogs.index', $amoAccount),
                    'lead_sync_schedules' => route('amo-accounts.lead-sync-schedules.index', $amoAccount),
                    'events_sync' => route('amo-accounts.events-sync.index', $amoAccount),
                    'task_statistics' => route('amo-accounts.task-statistics.index', $amoAccount),
                    'responsibility_redistribution' => route('amo-accounts.responsibility-redistribution.index', $amoAccount),
                    'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                ],
            ],
        ]);
    }
}
