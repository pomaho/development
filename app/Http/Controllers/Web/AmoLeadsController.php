<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineSnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Services\Exports\TableExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoLeadsController extends Controller
{
    public function __invoke(Request $request, AmoAccount $amoAccount): View
    {
        return view('amo-accounts.leads', [
            'account' => $amoAccount,
            'leads' => $this->filteredQuery($request, $amoAccount)->latest('entity_updated_at')->paginate(50)->withQueryString(),
            'pipelines' => $this->pipelines($amoAccount),
            'statuses' => $this->statuses($amoAccount),
            'responsibles' => $this->responsibles($amoAccount),
        ]);
    }

    public function export(Request $request, AmoAccount $amoAccount, TableExportService $export): StreamedResponse
    {
        $leads = $this->filteredQuery($request, $amoAccount)->latest('entity_updated_at')->get();

        return $export->csv("amo-leads-{$amoAccount->id}.csv", [
            'ID сделки',
            'Название',
            'Pipeline ID',
            'Воронка',
            'Status ID',
            'Этап',
            'Ответственный',
            'Создана',
            'Обновлена',
            'Закрыта',
            'Бюджет',
            'Теги',
            'Поля',
            'Raw',
        ], $leads->map(function (CrmEntitySnapshot $lead) use ($amoAccount): array {
            $raw = $lead->raw ?? [];

            return [
                $lead->external_id,
                $lead->name,
                $lead->pipeline_id,
                $this->pipelineName($amoAccount, $lead->pipeline_id),
                $lead->status_id,
                $this->statusName($amoAccount, $lead->pipeline_id, $lead->status_id),
                $lead->responsible_user_id,
                $lead->entity_created_at,
                $lead->entity_updated_at,
                $lead->entity_closed_at,
                $raw['price'] ?? null,
                $raw['_embedded']['tags'] ?? $lead->embedded['tags'] ?? null,
                $lead->custom_fields_values,
                $raw,
            ];
        }));
    }

    private function filteredQuery(Request $request, AmoAccount $amoAccount): Builder
    {
        $query = CrmEntitySnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->where('entity_type', 'leads');

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('external_id', 'like', $search));
        }

        if ($request->filled('pipeline_id')) {
            $query->where('pipeline_id', $request->input('pipeline_id'));
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->input('status_id'));
        }

        if ($request->filled('responsible_user_id')) {
            $query->where('responsible_user_id', $request->input('responsible_user_id'));
        }

        if ($request->filled('created_from')) {
            $query->where('entity_created_at', '>=', $request->date('created_from')->startOfDay());
        }

        if ($request->filled('created_to')) {
            $query->where('entity_created_at', '<=', $request->date('created_to')->endOfDay());
        }

        return $query;
    }

    private function pipelines(AmoAccount $account)
    {
        return CrmPipelineSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->orderBy('sort')
            ->get();
    }

    private function statuses(AmoAccount $account)
    {
        return CrmPipelineStatusSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->orderBy('sort')
            ->get();
    }

    private function responsibles(AmoAccount $account)
    {
        return CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->whereNotNull('responsible_user_id')
            ->distinct()
            ->orderBy('responsible_user_id')
            ->pluck('responsible_user_id');
    }

    private function pipelineName(AmoAccount $account, mixed $pipelineId): ?string
    {
        return $this->pipelines($account)->firstWhere('amo_pipeline_id', (int) $pipelineId)?->name;
    }

    private function statusName(AmoAccount $account, mixed $pipelineId, mixed $statusId): ?string
    {
        return $this->statuses($account)
            ->where('amo_pipeline_id', (int) $pipelineId)
            ->firstWhere('amo_status_id', (int) $statusId)?->name;
    }
}
