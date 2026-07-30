<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget\Clients\Eurohome;

use App\Http\Controllers\Controller;
use App\Models\AmoAccountDashboardWidget;
use App\Services\Amo\Analytics\Clients\Eurohome\AmoBudgetSegmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AmoBudgetSegmentController extends Controller
{
    public function data(Request $request, string $publicKey, AmoBudgetSegmentService $service): JsonResponse
    {
        $installation = $this->installation($publicKey);
        $tz = $installation->account->timezone();
        [$from, $to] = $this->period($request, $tz);

        return response()->json([
            'data' => $service->breakdown(
                $installation->account,
                $from,
                $to,
                $installation->config ?? [],
                $tz,
            ),
        ]);
    }

    public function leads(Request $request, string $publicKey, AmoBudgetSegmentService $service): JsonResponse
    {
        $installation = $this->installation($publicKey);
        $tz = $installation->account->timezone();
        [$from, $to] = $this->period($request, $tz);

        return response()->json([
            'data' => $service->leads(
                $installation->account,
                $from,
                $to,
                $installation->config ?? [],
                (string) $request->query('segment', ''),
                300,
                $tz,
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
                ->where('code', 'budget_segment_dashboard')
                ->where('is_enabled', true))
            ->firstOrFail();
    }

    private function period(Request $request, string $timezone): array
    {
        $from = $this->parseDate($request->query('from'), $timezone);
        $to = $this->parseDate($request->query('to'), $timezone);

        if ($from !== null || $to !== null) {
            return [
                ($from ?? now($timezone)->startOfMonth())->startOfDay(),
                ($to ?? now($timezone))->endOfDay(),
                ['source' => 'manual', 'preset' => null, 'label' => 'Выбранный период'],
            ];
        }

        return [
            now($timezone)->startOfMonth()->startOfDay(),
            now($timezone)->endOfDay(),
            ['source' => 'default', 'preset' => null, 'label' => 'Текущий период'],
        ];
    }

    private function parseDate(mixed $value, string $timezone): ?Carbon
    {
        if ($value === null || $value === '' || $value === 'null' || $value === 'false') {
            return null;
        }

        return is_numeric($value)
            ? Carbon::createFromTimestamp((int) $value, $timezone)
            : Carbon::parse((string) $value, $timezone);
    }
}
