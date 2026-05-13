<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloneAmoPipelineRequest;
use App\Http\Requests\StoreAmoPipelineRequest;
use App\Models\AmoAccount;
use App\Services\Amo\AmoPipelinesService;
use App\Services\Exports\TableExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoPipelinesController extends Controller
{
    public function index(Request $request, AmoAccount $amoAccount, AmoPipelinesService $pipelinesService): Response
    {
        $pipelines = [];
        $error = null;

        try {
            $pipelines = $this->filteredPipelines($request, $pipelinesService->fetchPipelines($amoAccount));
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return Inertia::render('AmoAccounts/Pipelines/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'pipelines' => collect($pipelines)->map(fn (array $pipeline): array => [
                'id' => $pipeline['id'] ?? null,
                'name' => $pipeline['name'] ?? '-',
                'is_main' => (bool) ($pipeline['is_main'] ?? false),
                'is_unsorted_on' => (bool) ($pipeline['is_unsorted_on'] ?? false),
                'is_archive' => (bool) ($pipeline['is_archive'] ?? false),
                'statuses' => collect($pipeline['_embedded']['statuses'] ?? [])->map(fn (array $status): array => [
                    'id' => $status['id'] ?? null,
                    'name' => $status['name'] ?? '-',
                ])->values(),
                'links' => isset($pipeline['id']) ? [
                    'show' => route('amo-accounts.pipelines.show', [$amoAccount, $pipeline['id']]),
                    'clone' => route('amo-accounts.pipelines.clone-form', [$amoAccount, $pipeline['id']]),
                ] : null,
            ])->values(),
            'error' => $error,
            'filters' => [
                'activity' => $request->string('activity')->toString(),
            ],
            'can' => [
                'sync' => $request->user()?->can('sync', $amoAccount) ?? false,
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'export' => route('amo-accounts.pipelines.export', array_merge(['amo_account' => $amoAccount], $request->query())),
                'reset' => route('amo-accounts.pipelines.index', $amoAccount),
                'create' => route('amo-accounts.pipelines.create', $amoAccount),
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

    public function export(
        Request $request,
        AmoAccount $amoAccount,
        AmoPipelinesService $pipelinesService,
        TableExportService $export
    ): StreamedResponse {
        $pipelines = $this->filteredPipelines($request, $pipelinesService->fetchPipelines($amoAccount));

        return $export->csv("amo-pipelines-{$amoAccount->id}.csv", [
            'ID',
            'Название',
            'Главная',
            'Неразобранное',
            'Архив',
            'Этапов',
            'Этапы',
        ], collect($pipelines)->map(fn (array $pipeline): array => [
            $pipeline['id'] ?? null,
            $pipeline['name'] ?? null,
            (bool) ($pipeline['is_main'] ?? false),
            (bool) ($pipeline['is_unsorted_on'] ?? false),
            (bool) ($pipeline['is_archive'] ?? false),
            count($pipeline['_embedded']['statuses'] ?? []),
            collect($pipeline['_embedded']['statuses'] ?? [])->pluck('name')->implode(', '),
        ]));
    }

    public function create(AmoAccount $amoAccount, AmoPipelinesService $pipelinesService): Response
    {
        $this->authorize('sync', $amoAccount);

        return Inertia::render('AmoAccounts/Pipelines/Create', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'defaultStatuses' => $pipelinesService->defaultStatuses(),
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'store' => route('amo-accounts.pipelines.store', $amoAccount),
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

    public function show(AmoAccount $amoAccount, int $pipelineId, AmoPipelinesService $pipelinesService): Response
    {
        $details = [
            'pipeline' => [],
            'statuses' => [],
            'stage_rows' => [],
            'lead_custom_fields' => [],
            'sources' => [],
            'all_sources' => [],
            'widgets' => [],
            'website_buttons' => [],
            'all_website_buttons' => [],
            'loss_reasons' => [],
            'errors' => [],
            'limitations' => [],
        ];
        $error = null;

        try {
            $details = $pipelinesService->fetchPipelineDetails($amoAccount, $pipelineId);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return Inertia::render('AmoAccounts/Pipelines/Show', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'pipelineId' => $pipelineId,
            'details' => $details,
            'error' => $error,
            'can' => [
                'sync' => request()->user()?->can('sync', $amoAccount) ?? false,
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'clone' => route('amo-accounts.pipelines.clone-form', [$amoAccount, $pipelineId]),
                'create' => route('amo-accounts.pipelines.create', $amoAccount),
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

    public function cloneForm(AmoAccount $amoAccount, int $pipelineId, AmoPipelinesService $pipelinesService): Response
    {
        $this->authorize('sync', $amoAccount);

        $details = [
            'pipeline' => [],
            'statuses' => [],
        ];
        $error = null;

        try {
            $details = $pipelinesService->fetchPipelineDetails($amoAccount, $pipelineId);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return Inertia::render('AmoAccounts/Pipelines/Clone', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'pipelineId' => $pipelineId,
            'pipeline' => $details['pipeline'] ?? [],
            'statuses' => $details['statuses'] ?? [],
            'error' => $error,
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'submit' => route('amo-accounts.pipelines.clone', [$amoAccount, $pipelineId]),
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

    public function store(
        StoreAmoPipelineRequest $request,
        AmoAccount $amoAccount,
        AmoPipelinesService $pipelinesService
    ): RedirectResponse {
        $this->authorize('sync', $amoAccount);

        $pipelinesService->createPipeline($amoAccount, $request->validatedPipelineData());

        return redirect()
            ->route('amo-accounts.pipelines.index', $amoAccount)
            ->with('status', 'Воронка отправлена в amoCRM.');
    }

    public function clone(
        CloneAmoPipelineRequest $request,
        AmoAccount $amoAccount,
        int $pipelineId,
        AmoPipelinesService $pipelinesService
    ): RedirectResponse {
        $this->authorize('sync', $amoAccount);

        try {
            $result = $pipelinesService->clonePipeline($amoAccount, $pipelineId, $request->validated('name'));
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['name' => 'Не удалось склонировать воронку: '.$exception->getMessage()]);
        }

        $warnings = $result['_clone_warnings'] ?? [];
        $status = 'Копия воронки отправлена в amoCRM.';
        if ($warnings !== []) {
            $status .= ' '.implode(' ', $warnings);
        }

        return redirect()
            ->route('amo-accounts.pipelines.index', $amoAccount)
            ->with('status', $status);
    }

    private function filteredPipelines(Request $request, array $pipelines): array
    {
        if (! $request->filled('activity')) {
            return $pipelines;
        }

        return collect($pipelines)
            ->filter(fn (array $pipeline): bool => match ($request->input('activity')) {
                'active' => ! (bool) ($pipeline['is_archive'] ?? false),
                'archived' => (bool) ($pipeline['is_archive'] ?? false),
                default => true,
            })
            ->values()
            ->all();
    }
}
