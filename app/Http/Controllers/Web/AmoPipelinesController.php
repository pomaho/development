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
