<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoAccountDashboardWidget;
use App\Models\DashboardWidget;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AmoAccountWidgetsController extends Controller
{
    public function __invoke(AmoAccount $amoAccount): Response
    {
        $widgets = DashboardWidget::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('AmoAccounts/Widgets', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'widgets' => $widgets
                ->map(function (DashboardWidget $widget) use ($amoAccount): array {
                    $installation = AmoAccountDashboardWidget::query()->firstOrCreate(
                        ['amo_account_id' => $amoAccount->id, 'dashboard_widget_id' => $widget->id],
                        ['public_key' => Str::random(48), 'is_enabled' => true]
                    );

                    return [
                        'id' => $widget->id,
                        'code' => $widget->code,
                        'name' => $widget->name,
                        'component_key' => $widget->component_key,
                        'sort_order' => $widget->sort_order,
                        'is_enabled' => $widget->is_enabled,
                        'installation' => [
                            'public_key' => $installation->public_key,
                            'is_enabled' => $installation->is_enabled,
                            'iframe_url' => $widget->code === 'task_overdue_dashboard'
                                ? route('widgets.amo.task-overdue-dashboard.show', $installation->public_key)
                                : null,
                            'api_url' => $widget->code === 'task_overdue_dashboard'
                                ? route('api.widgets.amo.task-overdue-dashboard.show', $installation->public_key)
                                : null,
                        ],
                    ];
                }),
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                ],
            ],
        ]);
    }
}
