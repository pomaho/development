<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineSnapshot;
use App\Models\ResponsibilityRedistributionRun;
use Inertia\Inertia;
use Inertia\Response;

class AmoAutomationCenterController extends Controller
{
    public function __invoke(AmoAccount $amoAccount): Response
    {
        $this->authorize('view', $amoAccount);

        return Inertia::render('AmoAccounts/AutomationCenter/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'summary' => [
                'pipelines_count' => CrmPipelineSnapshot::query()->where('amo_account_id', $amoAccount->id)->count(),
                'leads_count' => CrmEntitySnapshot::query()->where('amo_account_id', $amoAccount->id)->where('entity_type', 'leads')->count(),
                'responsibility_runs_count' => ResponsibilityRedistributionRun::query()->where('amo_account_id', $amoAccount->id)->count(),
                'failed_responsibility_runs_count' => ResponsibilityRedistributionRun::query()
                    ->where('amo_account_id', $amoAccount->id)
                    ->whereNotNull('error_message')
                    ->count(),
            ],
            'can' => [
                'sync' => request()->user()?->can('sync', $amoAccount) ?? false,
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
                    'automation_center' => route('amo-accounts.automation.index', $amoAccount),
                    'analytics_center' => route('amo-accounts.analytics.index', $amoAccount),
                    'sync_center' => route('amo-accounts.sync.index', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'pipelines_create' => route('amo-accounts.pipelines.create', $amoAccount),
                    'pipelines_transfer_leads' => route('amo-accounts.pipelines.transfer-leads', $amoAccount),
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
