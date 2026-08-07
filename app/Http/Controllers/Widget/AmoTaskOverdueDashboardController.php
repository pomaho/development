<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\AmoAccountDashboardWidget;
use App\Services\Amo\Analytics\AmoTaskStatisticsService;
use App\Services\Exports\WidgetExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'recruiterLeads' => $statisticsService->recruiterLeadDistribution($installation->account, $from, $to, $installation->config ?? []),
            'recruiterTeamCityBreakdown' => $statisticsService->recruiterTeamCityBreakdown($installation->account, $from, $to, $installation->config ?? []),
            'links' => [
                'self' => route('widgets.amo.task-overdue-dashboard.show', $publicKey),
                'api' => route('api.widgets.amo.task-overdue-dashboard.show', $publicKey),
            ],
        ]);
    }

    public function showV2(Request $request, string $publicKey): Response
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
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
            'links' => [
                'self' => route('widgets.amo.task-overdue-dashboard-v2.show', $publicKey),
                'recruiterLeads' => route('api.widgets.amo.task-overdue-dashboard-v2.recruiter-leads', $publicKey),
                'recruiterTeamCityBreakdown' => route('api.widgets.amo.task-overdue-dashboard-v2.recruiter-team-city-breakdown', $publicKey),
                'taskStatistics' => route('api.widgets.amo.task-overdue-dashboard-v2.task-statistics', $publicKey),
                'userOverdueTasks' => route('api.widgets.amo.task-overdue-dashboard-v2.user-overdue-tasks', $publicKey),
                'projectCityVacancy' => route('api.widgets.amo.task-overdue-dashboard-v2.project-city-vacancy', $publicKey),
                'projectCityVacancyLeads' => route('api.widgets.amo.task-overdue-dashboard-v2.project-city-vacancy-leads', $publicKey),
                'recruiterSchedule' => route('api.widgets.amo.task-overdue-dashboard-v2.recruiter-schedule', $publicKey),
                'managerLeads' => route('api.widgets.amo.task-overdue-dashboard-v2.manager-leads', $publicKey),
                'managerLeadsList' => route('api.widgets.amo.task-overdue-dashboard-v2.manager-leads-list', $publicKey),
                'avitoCabinetBreakdown' => route('api.widgets.amo.task-overdue-dashboard-v2.avito-cabinet-breakdown', $publicKey),
                'avitoCabinetLeads' => route('api.widgets.amo.task-overdue-dashboard-v2.avito-cabinet-leads', $publicKey),
                'shiftDateLeads' => route('api.widgets.amo.task-overdue-dashboard-v2.shift-date-leads', $publicKey),
                'export' => route('api.widgets.amo.task-overdue-dashboard-v2.export', $publicKey),
            ],
        ]);
    }

    public function recruiterLeads(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->recruiterLeadDistribution($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function recruiterTeamCityBreakdown(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->recruiterTeamCityBreakdown($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function taskStatistics(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->statistics($installation->account, $from, $to),
        ]);
    }

    public function projectCityVacancyBreakdown(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->projectCityVacancyBreakdown($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function projectCityVacancyLeads(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->projectCityVacancyLeads(
                $installation->account,
                $from,
                $to,
                $installation->config ?? [],
                (string) $request->query('project', ''),
                (string) $request->query('city', ''),
                (string) $request->query('vacancy', ''),
                (string) $request->query('source', ''),
                (string) $request->query('team', ''),
                (int) $request->query('recruiter_enum_id', 0),
                $request->query('manager_required', '1') !== '0',
                (int) $request->query('status_id', 0),
                200,
                $tz,
            ),
        ]);
    }

    public function recruiterScheduleBreakdown(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->recruiterScheduleBreakdown($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function managerLeads(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->managerLeadDistribution($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function managerLeadsList(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->managerLeads(
                $installation->account,
                $from,
                $to,
                $installation->config ?? [],
                (int) $request->query('manager_enum_id', 0),
                $request->query('scheduled_only', '0') === '1',
                200,
                $tz,
            ),
        ]);
    }

    public function avitoCabinetBreakdown(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->avitoCabinetBreakdown($installation->account, $from, $to),
        ]);
    }

    public function avitoCabinetLeads(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->avitoCabinetLeads(
                $installation->account,
                $from,
                $to,
                (string) $request->query('cabinet', ''),
                $request->query('success', '0') === '1',
                200,
            ),
        ]);
    }

    public function shiftDateLeads(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->shiftDateLeads($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function userOverdueTasks(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $userId = (int) $request->query('user_id', 0);

        return response()->json([
            'tasks' => $statisticsService->userOverdueTasks($installation->account, $userId, $from, $to),
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
            'recruiterLeads' => $statisticsService->recruiterLeadDistribution($installation->account, $from, $to, $installation->config ?? []),
            'recruiterTeamCityBreakdown' => $statisticsService->recruiterTeamCityBreakdown($installation->account, $from, $to, $installation->config ?? []),
        ]);
    }

    public function showV2Dev(Request $request, string $publicKey): Response
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to, $periodMeta] = $this->period($request);

        return Inertia::render('Widgets/Amo/TaskOverdueDashboardV2Dev', [
            'account' => [
                'name' => $installation->account->name,
                'base_domain' => $installation->account->base_domain,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                ...$periodMeta,
            ],
            'links' => [
                'self' => route('widgets.amo.task-overdue-dashboard-v2-dev.show', $publicKey),
                'recruiterLeads' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.recruiter-leads', $publicKey),
                'recruiterTeamCityBreakdown' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.recruiter-team-city-breakdown', $publicKey),
                'taskStatistics' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.task-statistics', $publicKey),
                'userOverdueTasks' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.user-overdue-tasks', $publicKey),
                'projectCityVacancy' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.project-city-vacancy', $publicKey),
                'projectCityVacancyLeads' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.project-city-vacancy-leads', $publicKey),
                'recruiterSchedule' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.recruiter-schedule', $publicKey),
                'managerLeads' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.manager-leads', $publicKey),
                'managerLeadsList' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.manager-leads-list', $publicKey),
                'avitoCabinetBreakdown' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.avito-cabinet-breakdown', $publicKey),
                'avitoCabinetLeads' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.avito-cabinet-leads', $publicKey),
                'shiftDateLeads' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.shift-date-leads', $publicKey),
                'export' => route('api.widgets.amo.task-overdue-dashboard-v2-dev.export', $publicKey),
            ],
        ]);
    }

    public function recruiterLeadsDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->recruiterLeadDistribution($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function recruiterTeamCityBreakdownDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->recruiterTeamCityBreakdown($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function taskStatisticsDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->statistics($installation->account, $from, $to),
        ]);
    }

    public function projectCityVacancyBreakdownDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->projectCityVacancyBreakdown($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function projectCityVacancyLeadsDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->projectCityVacancyLeads(
                $installation->account,
                $from,
                $to,
                $installation->config ?? [],
                (string) $request->query('project', ''),
                (string) $request->query('city', ''),
                (string) $request->query('vacancy', ''),
                (string) $request->query('source', ''),
                (string) $request->query('team', ''),
                (int) $request->query('recruiter_enum_id', 0),
                $request->query('manager_required', '1') !== '0',
                (int) $request->query('status_id', 0),
                200,
                $tz,
            ),
        ]);
    }

    public function recruiterScheduleBreakdownDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->recruiterScheduleBreakdown($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function managerLeadsDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->managerLeadDistribution($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function managerLeadsListDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->managerLeads(
                $installation->account,
                $from,
                $to,
                $installation->config ?? [],
                (int) $request->query('manager_enum_id', 0),
                $request->query('scheduled_only', '0') === '1',
                200,
                $tz,
            ),
        ]);
    }

    public function avitoCabinetBreakdownDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->avitoCabinetBreakdown($installation->account, $from, $to),
        ]);
    }

    public function shiftDateLeadsDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();

        return response()->json([
            'data' => $statisticsService->shiftDateLeads($installation->account, $from, $to, $installation->config ?? [], $tz),
        ]);
    }

    public function avitoCabinetLeadsDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->avitoCabinetLeads(
                $installation->account,
                $from,
                $to,
                (string) $request->query('cabinet', ''),
                $request->query('success', '0') === '1',
                200,
            ),
        ]);
    }

    public function userOverdueTasksDev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $userId = (int) $request->query('user_id', 0);

        return response()->json([
            'tasks' => $statisticsService->userOverdueTasks($installation->account, $userId, $from, $to),
        ]);
    }

    public function exportV2(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService, WidgetExcelExportService $excelExport): StreamedResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();
        $config = $installation->config ?? [];

        return $excelExport->export(
            WidgetExcelExportService::filename($from, $to),
            $statisticsService->recruiterLeadDistribution($installation->account, $from, $to, $config, $tz),
            $statisticsService->recruiterTeamCityBreakdown($installation->account, $from, $to, $config, $tz),
            $statisticsService->projectCityVacancyBreakdown($installation->account, $from, $to, $config, $tz),
            $statisticsService->statistics($installation->account, $from, $to),
        );
    }

    public function exportV2Dev(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService, WidgetExcelExportService $excelExport): StreamedResponse
    {
        $installation = $this->installation($publicKey, 'task_overdue_dashboard_v2_dev');
        [$from, $to] = $this->period($request);
        $tz = $installation->account->timezone();
        $config = $installation->config ?? [];

        return $excelExport->export(
            WidgetExcelExportService::filename($from, $to),
            $statisticsService->recruiterLeadDistribution($installation->account, $from, $to, $config, $tz),
            $statisticsService->recruiterTeamCityBreakdown($installation->account, $from, $to, $config, $tz),
            $statisticsService->projectCityVacancyBreakdown($installation->account, $from, $to, $config, $tz),
            $statisticsService->statistics($installation->account, $from, $to),
        );
    }

    private function installation(string $publicKey, string $widgetCode = 'task_overdue_dashboard'): AmoAccountDashboardWidget
    {
        return AmoAccountDashboardWidget::query()
            ->with(['account', 'widget'])
            ->where('public_key', $publicKey)
            ->where('is_enabled', true)
            ->whereHas('widget', fn ($query) => $query
                ->where('code', $widgetCode)
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
