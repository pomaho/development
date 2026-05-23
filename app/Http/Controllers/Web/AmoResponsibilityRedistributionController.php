<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\ResponsibilityRedistributionRun;
use App\Services\Amo\AmoResponsibilityRedistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AmoResponsibilityRedistributionController extends Controller
{
    public function index(AmoAccount $amoAccount, AmoResponsibilityRedistributionService $service): Response
    {
        return $this->renderPage($amoAccount, $service);
    }

    public function preview(Request $request, AmoAccount $amoAccount, AmoResponsibilityRedistributionService $service): Response
    {
        $this->authorize('sync', $amoAccount);

        $data = $this->validatedData($request);
        $preview = null;
        $error = null;

        try {
            $preview = $service->preview($amoAccount, (int) $data['source_user_id'], $data['target_user_ids'], (bool) $data['include_tasks']);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return $this->renderPage($amoAccount, $service, [
            'source_user_id' => (string) $data['source_user_id'],
            'target_user_ids' => array_map('strval', $data['target_user_ids']),
            'include_tasks' => (bool) $data['include_tasks'],
        ], $preview, $error);
    }

    public function store(Request $request, AmoAccount $amoAccount, AmoResponsibilityRedistributionService $service): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $this->validatedData($request);
        $run = ResponsibilityRedistributionRun::query()->create([
            'amo_account_id' => $amoAccount->id,
            'source_user_id' => (int) $data['source_user_id'],
            'target_user_ids' => $data['target_user_ids'],
            'status' => ResponsibilityRedistributionRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $preview = $service->preview($amoAccount, (int) $data['source_user_id'], $data['target_user_ids'], (bool) $data['include_tasks']);
            $result = $service->redistribute($amoAccount, (int) $data['source_user_id'], $data['target_user_ids'], (bool) $data['include_tasks']);

            $run->forceFill([
                'status' => ResponsibilityRedistributionRun::STATUS_COMPLETED,
                'preview' => $preview,
                'result' => $result,
                'finished_at' => now(),
            ])->save();

            return redirect()
                ->route('amo-accounts.responsibility-redistribution.index', $amoAccount)
                ->with('status', "Распределено контактов: {$result['updated_contacts']}. Сделок: {$result['updated_leads']}. Задач: {$result['updated_tasks']}.");
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => ResponsibilityRedistributionRun::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            return redirect()
                ->route('amo-accounts.responsibility-redistribution.index', $amoAccount)
                ->with('error', 'Не удалось выполнить распределение: '.$exception->getMessage());
        }
    }

    private function renderPage(
        AmoAccount $amoAccount,
        AmoResponsibilityRedistributionService $service,
        array $form = ['source_user_id' => '', 'target_user_ids' => [], 'include_tasks' => false],
        ?array $preview = null,
        ?string $error = null
    ): Response {
        $users = [];
        try {
            $users = $service->activeUsers($amoAccount);
        } catch (Throwable $exception) {
            $error ??= $exception->getMessage();
        }

        return Inertia::render('AmoAccounts/ResponsibilityRedistribution/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'users' => $users,
            'form' => $form,
            'preview' => $preview,
            'runs' => $amoAccount->responsibilityRedistributionRuns()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (ResponsibilityRedistributionRun $run): array => [
                    'id' => $run->id,
                    'source_user_id' => $run->source_user_id,
                    'target_user_ids' => $run->target_user_ids,
                    'status' => $run->status,
                    'result' => $run->result,
                    'error_message' => $run->error_message,
                    'created_at' => $run->created_at?->format('Y-m-d H:i:s'),
                    'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
                ]),
            'error' => $error,
            'can' => [
                'sync' => request()->user()?->can('sync', $amoAccount) ?? false,
            ],
            'links' => $this->links($amoAccount),
        ]);
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'source_user_id' => ['required', 'integer', 'min:1'],
            'target_user_ids' => ['required', 'array', 'min:1'],
            'target_user_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'include_tasks' => ['sometimes', 'boolean'],
        ]);

        $data['include_tasks'] = $request->boolean('include_tasks');

        return $data;
    }

    private function links(AmoAccount $amoAccount): array
    {
        return [
            'dashboard' => route('dashboard'),
            'amo_accounts' => route('amo-accounts.index'),
            'oauth' => route('amo-oauth.external.index'),
            'api_logs' => route('logs.api'),
            'logout' => route('logout'),
            'preview' => route('amo-accounts.responsibility-redistribution.preview', $amoAccount),
            'submit' => route('amo-accounts.responsibility-redistribution.store', $amoAccount),
            'current_account' => [
                'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                'show' => route('amo-accounts.show', $amoAccount),
                'users' => route('amo-accounts.users', $amoAccount),
                'roles' => route('amo-accounts.roles', $amoAccount),
                'leads' => route('amo-accounts.leads', $amoAccount),
                'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                'catalogs' => route('amo-accounts.catalogs.index', $amoAccount),
                'responsibility_redistribution' => route('amo-accounts.responsibility-redistribution.index', $amoAccount),
                'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                'integrations' => route('amo-accounts.integrations', $amoAccount),
                'widgets' => route('amo-accounts.widgets', $amoAccount),
            ],
        ];
    }
}
