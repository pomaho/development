<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAmoPipelineRequest;
use App\Models\AmoAccount;
use App\Services\Amo\AmoPipelinesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AmoPipelinesController extends Controller
{
    public function index(AmoAccount $amoAccount, AmoPipelinesService $pipelinesService): View
    {
        $pipelines = [];
        $error = null;

        try {
            $pipelines = $pipelinesService->fetchPipelines($amoAccount);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return view('amo-accounts.pipelines.index', [
            'account' => $amoAccount,
            'pipelines' => $pipelines,
            'error' => $error,
        ]);
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
}
