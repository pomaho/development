<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineSnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Services\Exports\TableExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoLeadsController extends Controller
{
    public function __invoke(Request $request, AmoAccount $amoAccount): Response
    {
        $pipelines = $this->pipelines($amoAccount);
        $statuses = $this->statuses($amoAccount);
        $responsibles = $this->responsibles($amoAccount);

        return Inertia::render('AmoAccounts/Leads', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'leads' => $this->filteredQuery($request, $amoAccount)
                ->latest('entity_updated_at')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (CrmEntitySnapshot $lead): array => [
                    'id' => $lead->id,
                    'external_id' => $lead->external_id,
                    'name' => $lead->name,
                    'pipeline_id' => $lead->pipeline_id,
                    'pipeline_name' => $pipelines->firstWhere('amo_pipeline_id', (int) $lead->pipeline_id)?->name,
                    'status_id' => $lead->status_id,
                    'status_name' => $statuses
                        ->where('amo_pipeline_id', (int) $lead->pipeline_id)
                        ->firstWhere('amo_status_id', (int) $lead->status_id)?->name,
                    'responsible_user_id' => $lead->responsible_user_id,
                    'responsible_name' => $responsibles->firstWhere('id', $lead->responsible_user_id)['name'] ?? null,
                    'entity_created_at' => $lead->entity_created_at?->toDateTimeString(),
                    'entity_updated_at' => $lead->entity_updated_at?->toDateTimeString(),
                    'price' => ($lead->raw ?? [])['price'] ?? null,
                    'custom_fields_values' => $lead->custom_fields_values ?? [],
                    'raw' => $lead->raw ?? [],
                ]),
            'pipelines' => $pipelines->map(fn (CrmPipelineSnapshot $pipeline): array => [
                'id' => $pipeline->amo_pipeline_id,
                'name' => $pipeline->name,
            ]),
            'statuses' => $statuses->map(fn (CrmPipelineStatusSnapshot $status): array => [
                'id' => $status->amo_status_id,
                'pipeline_id' => $status->amo_pipeline_id,
                'name' => $status->name,
            ]),
            'responsibles' => $responsibles,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'pipeline_id' => $request->filled('pipeline_id') ? (string) $request->input('pipeline_id') : '',
                'status_id' => $request->filled('status_id') ? (string) $request->input('status_id') : '',
                'responsible_user_id' => $request->filled('responsible_user_id') ? (string) $request->input('responsible_user_id') : '',
                'created_from' => $request->string('created_from')->toString(),
                'created_to' => $request->string('created_to')->toString(),
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'export' => route('amo-accounts.leads.export', array_merge(['amo_account' => $amoAccount], $request->query())),
                'reset' => route('amo-accounts.leads', $amoAccount),
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
                $this->responsibleName($amoAccount, $lead->responsible_user_id),
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
        $responsibleIds = CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->whereNotNull('responsible_user_id')
            ->distinct()
            ->orderBy('responsible_user_id')
            ->pluck('responsible_user_id');

        $users = AmoUsersSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->whereIn('amo_user_id', $responsibleIds)
            ->get()
            ->keyBy('amo_user_id');

        return $responsibleIds->map(fn ($responsibleId): array => [
            'id' => $responsibleId,
            'name' => $users->get($responsibleId)?->name,
        ]);
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

    private function responsibleName(AmoAccount $account, mixed $responsibleId): ?string
    {
        if (! $responsibleId) {
            return null;
        }

        $user = AmoUsersSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('amo_user_id', $responsibleId)
            ->first();

        return $user ? "{$user->name} ({$responsibleId})" : (string) $responsibleId;
    }
}
