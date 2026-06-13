<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use Inertia\Inertia;
use Inertia\Response;

class AmoDataCenterController extends Controller
{
    public function __invoke(AmoAccount $amoAccount): Response
    {
        $this->authorize('view', $amoAccount);

        $baseQuery = CrmEntitySnapshot::query()->where('amo_account_id', $amoAccount->id);

        return Inertia::render('AmoAccounts/DataCenter/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'summary' => [
                'leads_count' => (clone $baseQuery)->where('entity_type', 'leads')->count(),
                'contacts_count' => (clone $baseQuery)->where('entity_type', 'contacts')->count(),
                'companies_count' => (clone $baseQuery)->where('entity_type', 'companies')->count(),
                'tasks_count' => (clone $baseQuery)->where('entity_type', 'tasks')->count(),
                'events_count' => (clone $baseQuery)->where('entity_type', 'events')->count(),
                'last_synced_at' => (clone $baseQuery)->max('synced_at'),
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
                    'data_center' => route('amo-accounts.data.index', $amoAccount),
                    'crm_structure_center' => route('amo-accounts.crm-structure.index', $amoAccount),
                    'automation_center' => route('amo-accounts.automation.index', $amoAccount),
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
                    'crm_fields' => route('amo-accounts.crm-audit.fields', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                ],
            ],
        ]);
    }
}
