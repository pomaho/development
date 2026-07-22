<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget\Clients\Eurohome;

use App\Http\Controllers\Controller;
use App\Models\AmoAccountDashboardWidget;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

// Composition-only widget: aggregates the already-existing manager-topup and
// product-group widgets on one page. Reuses their own routes/services as-is —
// no duplicated business logic, no new data/leads endpoints.
class AmoClientDashboardController extends Controller
{
    public function show(Request $request, string $publicKey): Response
    {
        $installation = $this->installation($publicKey);
        $account = $installation->account;
        $tz = $account->timezone();
        [$from, $to, $periodMeta] = $this->period($request, $tz);

        $managerTopup = $this->siblingInstallation($account->id, 'manager_topup_dashboard');
        $productGroup = $this->siblingInstallation($account->id, 'product_group_dashboard');

        return Inertia::render('Widgets/Amo/Clients/Eurohome/ClientDashboard', [
            'account' => [
                'name' => $account->name,
                'base_domain' => $account->base_domain,
                'timezone' => $tz,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                ...$periodMeta,
            ],
            'sections' => [
                'managerTopup' => $managerTopup === null ? null : [
                    'links' => [
                        'data' => route('api.widgets.amo.manager-topup.data', $managerTopup->public_key),
                        'leads' => route('api.widgets.amo.manager-topup.leads', $managerTopup->public_key),
                        'designers' => route('api.widgets.amo.manager-topup.designers', $managerTopup->public_key),
                        'designerLeads' => route('api.widgets.amo.manager-topup.designers-leads', $managerTopup->public_key),
                    ],
                ],
                'productGroup' => $productGroup === null ? null : [
                    'links' => [
                        'data' => route('api.widgets.amo.product-group.data', $productGroup->public_key),
                        'leads' => route('api.widgets.amo.product-group.leads', $productGroup->public_key),
                    ],
                ],
            ],
        ]);
    }

    private function installation(string $publicKey): AmoAccountDashboardWidget
    {
        return AmoAccountDashboardWidget::query()
            ->with('account')
            ->where('public_key', $publicKey)
            ->where('is_enabled', true)
            ->whereHas('widget', fn ($q) => $q
                ->where('code', 'eurohome_client_dashboard')
                ->where('is_enabled', true))
            ->firstOrFail();
    }

    private function siblingInstallation(int $accountId, string $widgetCode): ?AmoAccountDashboardWidget
    {
        return AmoAccountDashboardWidget::query()
            ->where('amo_account_id', $accountId)
            ->where('is_enabled', true)
            ->whereNotNull('config')
            ->whereHas('widget', fn ($q) => $q
                ->where('code', $widgetCode)
                ->where('is_enabled', true))
            ->first();
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
