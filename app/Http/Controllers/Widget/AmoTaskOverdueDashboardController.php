<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\AmoAccountDashboardWidget;
use App\Services\Amo\AmoTaskStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AmoTaskOverdueDashboardController extends Controller
{
    public function show(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): Response
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        return Inertia::render('Widgets/Amo/TaskOverdueDashboard', [
            'account' => [
                'name' => $installation->account->name,
                'base_domain' => $installation->account->base_domain,
            ],
            'widget' => [
                'name' => $installation->widget->name,
                'code' => $installation->widget->code,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'groups' => $statisticsService->completedOverdueDashboard($installation->account, $from, $to),
            'recruiterLeads' => $statisticsService->recruiterLeadDistribution($installation->account, $from, $to),
            'links' => [
                'self' => route('widgets.amo.task-overdue-dashboard.show', $publicKey),
                'api' => route('api.widgets.amo.task-overdue-dashboard.show', $publicKey),
            ],
        ]);
    }

    public function json(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        return response()->json([
            'account' => [
                'name' => $installation->account->name,
                'base_domain' => $installation->account->base_domain,
            ],
            'widget' => [
                'name' => $installation->widget->name,
                'code' => $installation->widget->code,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'groups' => $statisticsService->completedOverdueDashboard($installation->account, $from, $to),
            'recruiterLeads' => $statisticsService->recruiterLeadDistribution($installation->account, $from, $to),
        ]);
    }

    private function installation(string $publicKey): AmoAccountDashboardWidget
    {
        return AmoAccountDashboardWidget::query()
            ->with(['account', 'widget'])
            ->where('public_key', $publicKey)
            ->where('is_enabled', true)
            ->whereHas('widget', fn ($query) => $query
                ->where('code', 'task_overdue_dashboard')
                ->where('is_enabled', true))
            ->firstOrFail();
    }

    private function period(Request $request): array
    {
        return [
            $request->filled('from') ? $request->date('from')->startOfDay() : now()->startOfMonth(),
            $request->filled('to') ? $request->date('to')->endOfDay() : now()->endOfDay(),
        ];
    }
}
