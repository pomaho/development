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
use Illuminate\View\View;

class CrmAuditController extends Controller
{
    public function index(AmoAccount $amoAccount, CrmAuditService $auditService): View
    {
        return view('amo-accounts.crm-audit.index', [
            'account' => $amoAccount,
            'summary' => $auditService->auditSummary($amoAccount),
            'pipelines' => CrmPipelineSnapshot::query()->where('amo_account_id', $amoAccount->id)->orderBy('sort')->get(),
            'fields' => CrmCustomFieldSnapshot::query()->where('amo_account_id', $amoAccount->id)->orderBy('entity_type')->orderBy('sort')->limit(100)->get(),
            'recentEntities' => CrmEntitySnapshot::query()->where('amo_account_id', $amoAccount->id)->latest('synced_at')->limit(20)->get(),
            'statusesCount' => CrmPipelineStatusSnapshot::query()->where('amo_account_id', $amoAccount->id)->count(),
        ]);
    }

    public function sync(Request $request, AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'structure_only' => ['nullable', 'boolean'],
        ]);

        SyncCrmAuditJob::dispatch(
            $amoAccount->id,
            $data['from'] ?? null,
            $data['to'] ?? null,
            $request->boolean('structure_only')
        );

        return back()->with('status', 'CRM-аудит поставлен в очередь.');
    }
}
