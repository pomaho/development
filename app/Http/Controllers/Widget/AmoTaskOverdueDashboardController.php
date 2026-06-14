<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\AmoAccountDashboardWidget;
use App\Services\Amo\Analytics\AmoTaskStatisticsService;
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
        [$from, $to, $periodMeta] = $this->period($request);

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
                ...$periodMeta,
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

    public function showV2(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): Response
    {
        $installation = $this->installation($publicKey);
        [$from, $to, $periodMeta] = $this->period($request);

        return Inertia::render('Widgets/Amo/TaskOverdueDashboardV2', [
            'account' => [
                'name' => $installation->account->name,
                'base_domain' => $installation->account->base_domain,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                ...$periodMeta,
            ],
            'groups' => $statisticsService->completedOverdueDashboard($installation->account, $from, $to),
            'recruiterLeads' => $statisticsService->recruiterLeadDistribution($installation->account, $from, $to, $installation->config ?? []),
            'recruiterTeamCityBreakdown' => $statisticsService->recruiterTeamCityBreakdown($installation->account, $from, $to, $installation->config ?? []),
            'links' => [
                'self' => route('widgets.amo.task-overdue-dashboard-v2.show', $publicKey),
                'api' => route('api.widgets.amo.task-overdue-dashboard.show', $publicKey),
            ],
        ]);
    }

    public function json(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to, $periodMeta] = $this->period($request);

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
                ...$periodMeta,
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
        $from = $this->dateValue($request->query('from'));
        $to = $this->dateValue($request->query('to'));

        if ($from !== null || $to !== null) {
            return [
                ($from ?? now()->startOfMonth())->startOfDay(),
                ($to ?? now())->endOfDay(),
                ['source' => 'manual', 'preset' => null, 'label' => 'Выбранный период'],
            ];
        }

        $from = $this->dateValue($request->query('date_from'));
        $to = $this->dateValue($request->query('date_to'));

        if ($from !== null || $to !== null) {
            return [
                ($from ?? now()->startOfMonth())->startOfDay(),
                ($to ?? now())->endOfDay(),
                ['source' => 'amo_dates', 'preset' => null, 'label' => 'Период рабочего стола'],
            ];
        }

        $preset = (string) $request->query('period', '');

        return match ($preset) {
            'today', 'day' => [now()->startOfDay(), now()->endOfDay(), ['source' => 'amo_period', 'preset' => $preset, 'label' => 'Сегодня']],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), ['source' => 'amo_period', 'preset' => $preset, 'label' => 'Вчера']],
            'week' => [now()->startOfWeek()->startOfDay(), now()->endOfWeek()->endOfDay(), ['source' => 'amo_period', 'preset' => $preset, 'label' => 'Эта неделя']],
            'month' => [now()->startOfMonth()->startOfDay(), now()->endOfMonth()->endOfDay(), ['source' => 'amo_period', 'preset' => $preset, 'label' => 'Этот месяц']],
            'quarter' => [now()->startOfQuarter()->startOfDay(), now()->endOfQuarter()->endOfDay(), ['source' => 'amo_period', 'preset' => $preset, 'label' => 'Этот квартал']],
            'year' => [now()->startOfYear()->startOfDay(), now()->endOfYear()->endOfDay(), ['source' => 'amo_period', 'preset' => $preset, 'label' => 'Этот год']],
            default => [now()->startOfMonth(), now()->endOfDay(), ['source' => 'default', 'preset' => null, 'label' => 'Текущий месяц']],
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
