<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCrmAuditJob;
use App\Models\AmoAccount;
use App\Models\CrmCustomFieldSnapshot;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineSnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Services\Amo\CrmAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmAuditController extends Controller
{
    public function index(AmoAccount $amoAccount, CrmAuditService $auditService): Response
    {
        $pipelines = CrmPipelineSnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->orderBy('sort')
            ->get();

        return Inertia::render('AmoAccounts/CrmAudit/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'summary' => $auditService->auditSummary($amoAccount),
            'pipelines' => $pipelines->map(fn (CrmPipelineSnapshot $pipeline): array => [
                'id' => $pipeline->id,
                'amo_pipeline_id' => $pipeline->amo_pipeline_id,
                'name' => $pipeline->name,
                'is_main' => $pipeline->is_main,
                'is_unsorted_on' => $pipeline->is_unsorted_on,
            ]),
            'fields' => CrmCustomFieldSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->orderBy('entity_type')
                ->orderBy('sort')
                ->limit(100)
                ->get()
                ->map(fn (CrmCustomFieldSnapshot $field): array => [
                    'id' => $field->id,
                    'entity_type' => $field->entity_type,
                    'amo_field_id' => $field->amo_field_id,
                    'name' => $field->name,
                    'field_type' => $field->field_type,
                ]),
            'recentEntities' => CrmEntitySnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->latest('synced_at')
                ->limit(20)
                ->get()
                ->map(fn (CrmEntitySnapshot $entity): array => [
                    'id' => $entity->id,
                    'entity_type' => $entity->entity_type,
                    'external_id' => $entity->external_id,
                    'name' => $entity->name,
                    'pipeline_id' => $entity->pipeline_id,
                    'status_id' => $entity->status_id,
                    'synced_at' => $entity->synced_at?->toDateTimeString(),
                    'raw' => $entity->raw ?? [],
                ]),
            'statusesCount' => CrmPipelineStatusSnapshot::query()->where('amo_account_id', $amoAccount->id)->count(),
            'can' => [
                'sync' => request()->user()?->can('sync', $amoAccount) ?? false,
            ],
            'defaults' => [
                'from' => now()->subMonths(6)->format('Y-m-d'),
                'to' => now()->format('Y-m-d'),
                'pipeline_id' => '',
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'sync' => route('amo-accounts.crm-audit.sync', $amoAccount),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                ],
            ],
        ]);
    }

    public function sync(Request $request, AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'pipeline_id' => ['nullable', 'integer', 'min:1'],
            'structure_only' => ['nullable', 'boolean'],
        ]);

        SyncCrmAuditJob::dispatch(
            $amoAccount->id,
            $data['from'] ?? null,
            $data['to'] ?? null,
            $request->boolean('structure_only'),
            isset($data['pipeline_id']) ? (int) $data['pipeline_id'] : null
        );

        $message = isset($data['pipeline_id'])
            ? "CRM-аудит по воронке {$data['pipeline_id']} поставлен в очередь."
            : 'CRM-аудит поставлен в очередь.';

        return back()->with('status', $message);
    }
}
