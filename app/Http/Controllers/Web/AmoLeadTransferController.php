<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\CrmPipelineSnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Services\Amo\Automation\AmoLeadTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AmoLeadTransferController extends Controller
{
    public function index(Request $request, AmoAccount $amoAccount, AmoLeadTransferService $transferService): Response
    {
        $pipelines = $this->pipelines($amoAccount);
        $sourcePipelineId = $request->integer('source_pipeline_id');
        $targetPipelineId = $request->integer('target_pipeline_id');
        $statusMap = $this->statusMap($request);
        $plan = null;

        if ($sourcePipelineId > 0 && $targetPipelineId > 0 && $sourcePipelineId !== $targetPipelineId) {
            $plan = $transferService->plan($amoAccount, $sourcePipelineId, $targetPipelineId, $statusMap);
        }

        return Inertia::render('AmoAccounts/Pipelines/TransferLeads', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'pipelines' => $pipelines->map(fn (CrmPipelineSnapshot $pipeline): array => [
                'id' => $pipeline->amo_pipeline_id,
                'name' => $pipeline->name,
            ]),
            'statuses' => CrmPipelineStatusSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->orderBy('amo_pipeline_id')
                ->orderBy('sort')
                ->get()
                ->map(fn (CrmPipelineStatusSnapshot $status): array => [
                    'id' => $status->amo_status_id,
                    'pipeline_id' => $status->amo_pipeline_id,
                    'name' => $status->name,
                ]),
            'filters' => [
                'source_pipeline_id' => $sourcePipelineId > 0 ? (string) $sourcePipelineId : '',
                'target_pipeline_id' => $targetPipelineId > 0 ? (string) $targetPipelineId : '',
                'status_map' => $statusMap,
            ],
            'plan' => $plan,
            'can' => [
                'sync' => $request->user()?->can('sync', $amoAccount) ?? false,
            ],
            'links' => $this->links($amoAccount),
        ]);
    }

    public function store(Request $request, AmoAccount $amoAccount, AmoLeadTransferService $transferService): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $request->validate([
            'source_pipeline_id' => ['required', 'integer', 'min:1', 'different:target_pipeline_id'],
            'target_pipeline_id' => ['required', 'integer', 'min:1'],
            'status_map' => ['required', 'array', 'min:1'],
            'status_map.*' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $transferService->transfer(
            $amoAccount,
            (int) $data['source_pipeline_id'],
            (int) $data['target_pipeline_id'],
            $this->cleanStatusMap($data['status_map'])
        );

        return redirect()
            ->route('amo-accounts.pipelines.transfer-leads', [
                'amo_account' => $amoAccount,
                'source_pipeline_id' => $data['source_pipeline_id'],
                'target_pipeline_id' => $data['target_pipeline_id'],
            ])
            ->with('status', "Перенесено сделок: {$result['updated']}. Пропущено: {$result['skipped']}.");
    }

    private function pipelines(AmoAccount $account)
    {
        return CrmPipelineSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('is_archive', false)
            ->orderBy('sort')
            ->get();
    }

    private function statusMap(Request $request): array
    {
        $rawMap = $request->input('status_map', []);

        return is_array($rawMap) ? $this->cleanStatusMap($rawMap) : [];
    }

    private function cleanStatusMap(array $statusMap): array
    {
        return collect($statusMap)
            ->filter(fn ($targetStatusId): bool => filled($targetStatusId))
            ->mapWithKeys(fn ($targetStatusId, $sourceStatusId): array => [(int) $sourceStatusId => (int) $targetStatusId])
            ->all();
    }

    private function links(AmoAccount $amoAccount): array
    {
        return [
            'dashboard' => route('dashboard'),
            'amo_accounts' => route('amo-accounts.index'),
            'oauth' => route('amo-oauth.external.index'),
            'api_logs' => route('logs.api'),
            'logout' => route('logout'),
            'submit' => route('amo-accounts.pipelines.transfer-leads.store', $amoAccount),
            'preview' => route('amo-accounts.pipelines.transfer-leads', $amoAccount),
            'current_account' => [
                'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                'show' => route('amo-accounts.show', $amoAccount),
                'users' => route('amo-accounts.users', $amoAccount),
                'roles' => route('amo-accounts.roles', $amoAccount),
                'leads' => route('amo-accounts.leads', $amoAccount),
                'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                'catalogs' => route('amo-accounts.catalogs.index', $amoAccount),
                'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                'integrations' => route('amo-accounts.integrations', $amoAccount),
                'widgets' => route('amo-accounts.widgets', $amoAccount),
            ],
        ];
    }
}
