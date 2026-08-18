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

class AmoManagerPipelineDashboardController extends Controller
{
    private const WIDGET_CODE = 'manager_pipeline_dashboard';

    public function show(Request $request, string $publicKey): Response
    {
        $installation = $this->installation($publicKey);
        [$from, $to, $periodMeta] = $this->period($request);

        return Inertia::render('Widgets/Amo/ManagerPipelineDashboard', [
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
                'self' => route('widgets.amo.manager-pipeline-dashboard.show', $publicKey),
                'overview' => route('api.widgets.amo.manager-pipeline-dashboard.overview', $publicKey),
                'overviewLeads' => route('api.widgets.amo.manager-pipeline-dashboard.overview-leads', $publicKey),
                'shiftBreakdown' => route('api.widgets.amo.manager-pipeline-dashboard.shift-breakdown', $publicKey),
                'shiftLeads' => route('api.widgets.amo.manager-pipeline-dashboard.shift-leads', $publicKey),
                'avitoCabinetBreakdown' => route('api.widgets.amo.manager-pipeline-dashboard.avito-cabinet-breakdown', $publicKey),
                'avitoCabinetLeads' => route('api.widgets.amo.manager-pipeline-dashboard.avito-cabinet-leads', $publicKey),
                'export' => route('api.widgets.amo.manager-pipeline-dashboard.export', $publicKey),
            ],
        ]);
    }

    public function overview(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->managerPipelineOverview($installation->account, $from, $to),
        ]);
    }

    public function overviewLeads(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->managerPipelineLeads(
                $installation->account,
                $from,
                $to,
                (string) $request->query('manager', ''),
                $request->boolean('success_only'),
            ),
        ]);
    }

    public function shiftBreakdown(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->managerPipelineShiftBreakdown($installation->account, $from, $to),
        ]);
    }

    public function shiftLeads(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->managerPipelineLeads(
                $installation->account,
                $from,
                $to,
                (string) $request->query('manager', ''),
                true,
                $request->boolean('fifth_shift_only'),
            ),
        ]);
    }

    public function avitoCabinetBreakdown(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->managerPipelineAvitoCabinetBreakdown($installation->account, $from, $to),
        ]);
    }

    public function avitoCabinetLeads(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $statisticsService->managerPipelineAvitoCabinetLeads(
                $installation->account,
                $from,
                $to,
                (string) $request->query('cabinet', ''),
                $request->boolean('success_only'),
            ),
        ]);
    }

    public function export(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService, WidgetExcelExportService $excelExport): StreamedResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        $overview = $statisticsService->managerPipelineOverview($installation->account, $from, $to);
        $shift = $statisticsService->managerPipelineShiftBreakdown($installation->account, $from, $to);
        $avitoCabinets = $statisticsService->managerPipelineAvitoCabinetBreakdown($installation->account, $from, $to);

        return $excelExport->exportFlatOnly(WidgetExcelExportService::filename($from, $to), [
            [
                'title' => 'Менеджеры — конверсия',
                'columns' => [
                    'name' => 'Менеджер',
                    'total_count' => 'Сделок за период',
                    'success_count' => 'Встал в график',
                    'conversion_rate' => 'Конверсия, %',
                ],
                'rows' => $overview['rows'],
                'totals' => true,
            ],
            [
                'title' => 'Менеджеры — смены',
                'columns' => [
                    'name' => 'Менеджер',
                    'shift_count' => 'Выведено на смену',
                    'fifth_shift_count' => 'Вышло на 5 смену',
                ],
                'rows' => $shift['rows'],
                'totals' => true,
            ],
            [
                'title' => 'Кабинеты Авито',
                'columns' => [
                    'name' => 'Кабинет Авито',
                    'total_count' => 'Лидов',
                    'success_count' => 'Встал в график',
                ],
                'rows' => $avitoCabinets['cabinets'],
                'totals' => true,
            ],
        ]);
    }

    private function installation(string $publicKey): AmoAccountDashboardWidget
    {
        return AmoAccountDashboardWidget::query()
            ->with(['account', 'widget'])
            ->where('public_key', $publicKey)
            ->where('is_enabled', true)
            ->whereHas('widget', fn ($query) => $query
                ->where('code', self::WIDGET_CODE)
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

        return [
            now()->startOfMonth()->startOfDay(),
            now()->endOfDay(),
            ['source' => 'default', 'preset' => null, 'label' => 'Текущий месяц'],
        ];
    }

    private function dateValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === false || $value === 'false' || $value === 'null') {
            return null;
        }

        return is_numeric($value)
            ? Carbon::createFromTimestamp((int) $value)
            : Carbon::parse((string) $value);
    }
}
