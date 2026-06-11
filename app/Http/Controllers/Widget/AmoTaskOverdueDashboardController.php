<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\AmoAccountDashboardWidget;
use App\Services\Amo\AmoTaskStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'recruiterLeads' => $statisticsService->recruiterLeadDistribution($installation->account, $from, $to, $installation->config ?? []),
            'recruiterTeamCityBreakdown' => $statisticsService->recruiterTeamCityBreakdown($installation->account, $from, $to, $installation->config ?? []),
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
            'recruiterLeads' => $statisticsService->recruiterLeadDistribution($installation->account, $from, $to, $installation->config ?? []),
            'recruiterTeamCityBreakdown' => $statisticsService->recruiterTeamCityBreakdown($installation->account, $from, $to, $installation->config ?? []),
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
        $from = $this->dateValue($request->query('from')) ?? $this->dateValue($request->query('date_from'));
        $to = $this->dateValue($request->query('to')) ?? $this->dateValue($request->query('date_to'));

        if ($from !== null || $to !== null) {
            return [
                ($from ?? now()->startOfMonth())->startOfDay(),
                ($to ?? now())->endOfDay(),
            ];
        }

        return match ((string) $request->query('period', '')) {
            'today', 'day' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'week' => [now()->startOfWeek()->startOfDay(), now()->endOfWeek()->endOfDay()],
            'month' => [now()->startOfMonth()->startOfDay(), now()->endOfMonth()->endOfDay()],
            'quarter' => [now()->startOfQuarter()->startOfDay(), now()->endOfQuarter()->endOfDay()],
            'year' => [now()->startOfYear()->startOfDay(), now()->endOfYear()->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfDay()],
        };
    }

    private function dateValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === false || $value === 'false' || $value === 'null') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return Carbon::parse((string) $value);
    }
}
