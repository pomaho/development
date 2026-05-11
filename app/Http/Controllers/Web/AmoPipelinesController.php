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
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoPipelinesController extends Controller
{
    public function index(Request $request, AmoAccount $amoAccount, AmoPipelinesService $pipelinesService): View
    {
        $pipelines = [];
        $error = null;

        try {
            $pipelines = $this->filteredPipelines($request, $pipelinesService->fetchPipelines($amoAccount));
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return view('amo-accounts.pipelines.index', [
            'account' => $amoAccount,
            'pipelines' => $pipelines,
            'error' => $error,
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

    public function create(AmoAccount $amoAccount, AmoPipelinesService $pipelinesService): View
    {
        $this->authorize('sync', $amoAccount);

        return view('amo-accounts.pipelines.create', [
            'account' => $amoAccount,
            'defaultStatuses' => $pipelinesService->defaultStatuses(),
        ]);
    }

    public function show(AmoAccount $amoAccount, int $pipelineId, AmoPipelinesService $pipelinesService): View
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

        return view('amo-accounts.pipelines.show', [
            'account' => $amoAccount,
            'pipelineId' => $pipelineId,
            'details' => $details,
            'error' => $error,
        ]);
    }

    public function cloneForm(AmoAccount $amoAccount, int $pipelineId, AmoPipelinesService $pipelinesService): View
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

        return view('amo-accounts.pipelines.clone', [
            'account' => $amoAccount,
            'pipelineId' => $pipelineId,
            'pipeline' => $details['pipeline'] ?? [],
            'statuses' => $details['statuses'] ?? [],
            'error' => $error,
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
