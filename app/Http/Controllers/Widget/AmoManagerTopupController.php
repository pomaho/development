<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\AmoAccountDashboardWidget;
use App\Services\Amo\Analytics\AmoManagerTopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AmoManagerTopupController extends Controller
{
    public function show(Request $request, string $publicKey): Response
    {
        $installation = $this->installation($publicKey);
        [$from, $to, $periodMeta] = $this->period($request);

        return Inertia::render('Widgets/Amo/ManagerTopupDashboard', [
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
                'self' => route('widgets.amo.manager-topup.show', $publicKey),
                'data' => route('api.widgets.amo.manager-topup.data', $publicKey),
                'leads' => route('api.widgets.amo.manager-topup.leads', $publicKey),
            ],
        ]);
    }

    public function data(Request $request, string $publicKey, AmoManagerTopupService $service): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        $selectedManagers = array_filter(
            explode(',', (string) $request->query('managers', '')),
            fn (string $m): bool => $m !== '',
        );

        return response()->json([
            'data' => $service->breakdown(
                $installation->account,
                $from,
                $to,
                $installation->config ?? [],
                array_values($selectedManagers),
            ),
        ]);
    }

    public function leads(Request $request, string $publicKey, AmoManagerTopupService $service): JsonResponse
    {
        $installation = $this->installation($publicKey);
        [$from, $to] = $this->period($request);

        return response()->json([
            'data' => $service->leads(
                $installation->account,
                $from,
                $to,
                $installation->config ?? [],
                (string) $request->query('manager', ''),
            ),
        ]);
    }

    private function installation(string $publicKey): AmoAccountDashboardWidget
    {
        return AmoAccountDashboardWidget::query()
            ->with(['account', 'widget'])
            ->where('public_key', $publicKey)
            ->where('is_enabled', true)
            ->whereHas('widget', fn ($q) => $q
                ->where('code', 'manager_topup_dashboard')
                ->where('is_enabled', true))
            ->firstOrFail();
    }

    private function period(Request $request): array
    {
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

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

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === 'null' || $value === 'false') {
            return null;
        }

        return is_numeric($value)
            ? Carbon::createFromTimestamp((int) $value)
            : Carbon::parse((string) $value);
    }
}
