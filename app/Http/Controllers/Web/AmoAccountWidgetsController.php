<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoAccountDashboardWidget;
use App\Models\CrmCustomFieldSnapshot;
use App\Models\CrmPipelineSnapshot;
use App\Models\DashboardWidget;
use App\Services\Amo\AmoTaskStatisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                            'settings_url' => route('amo-accounts.widgets.settings', [$amoAccount, $widget]),
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

    public function settings(AmoAccount $amoAccount, DashboardWidget $dashboardWidget, AmoTaskStatisticsService $statisticsService): Response
    {
        $this->authorize('update', $amoAccount);

        $installation = $this->installation($amoAccount, $dashboardWidget);
        $config = [
            'pipeline_id' => data_get($installation->config, 'pipeline_id'),
            'pipeline_name' => data_get($installation->config, 'pipeline_name'),
            'recruiter_field_id' => data_get($installation->config, 'recruiter_field_id'),
            'recruiter_field_name' => data_get($installation->config, 'recruiter_field_name'),
            'manager_field_id' => data_get($installation->config, 'manager_field_id'),
            'manager_field_name' => data_get($installation->config, 'manager_field_name'),
            'team_field_id' => data_get($installation->config, 'team_field_id'),
            'team_field_name' => data_get($installation->config, 'team_field_name'),
            'city_field_id' => data_get($installation->config, 'city_field_id'),
            'city_field_name' => data_get($installation->config, 'city_field_name'),
        ];

        return Inertia::render('AmoAccounts/Widgets/Settings', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'widget' => [
                'id' => $dashboardWidget->id,
                'code' => $dashboardWidget->code,
                'name' => $dashboardWidget->name,
            ],
            'config' => [
                'pipeline_id' => $config['pipeline_id'],
                'recruiter_field_id' => $config['recruiter_field_id'],
                'manager_field_id' => $config['manager_field_id'],
                'team_field_id' => $config['team_field_id'],
                'city_field_id' => $config['city_field_id'],
            ],
            'diagnostics' => $statisticsService->recruiterLeadDistributionDiagnostics($amoAccount, null, null, $config),
            'pipelines' => CrmPipelineSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->orderBy('sort')
                ->orderBy('name')
                ->get()
                ->map(fn (CrmPipelineSnapshot $pipeline): array => [
                    'id' => $pipeline->amo_pipeline_id,
                    'name' => $pipeline->name,
                    'is_archive' => $pipeline->is_archive,
                ]),
            'leadFields' => CrmCustomFieldSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->where('entity_type', 'leads')
                ->orderBy('sort')
                ->orderBy('name')
                ->get()
                ->map(fn (CrmCustomFieldSnapshot $field): array => [
                    'id' => $field->amo_field_id,
                    'name' => $field->name,
                    'field_type' => $field->field_type,
                ]),
            'links' => $this->links($amoAccount) + [
                'widgets' => route('amo-accounts.widgets', $amoAccount),
                'save' => route('amo-accounts.widgets.settings.update', [$amoAccount, $dashboardWidget]),
                'crm_fields' => route('amo-accounts.crm-audit.fields', $amoAccount),
            ],
        ]);
    }

    public function updateSettings(Request $request, AmoAccount $amoAccount, DashboardWidget $dashboardWidget): RedirectResponse
    {
        $this->authorize('update', $amoAccount);

        $data = $request->validate([
            'pipeline_id' => ['nullable', 'integer', 'min:1'],
            'recruiter_field_id' => ['nullable', 'integer', 'min:1'],
            'manager_field_id' => ['nullable', 'integer', 'min:1'],
            'team_field_id' => ['nullable', 'integer', 'min:1'],
            'city_field_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $pipeline = isset($data['pipeline_id'])
            ? CrmPipelineSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->where('amo_pipeline_id', (int) $data['pipeline_id'])
                ->first()
            : null;
        $field = isset($data['recruiter_field_id'])
            ? CrmCustomFieldSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->where('entity_type', 'leads')
                ->where('amo_field_id', (int) $data['recruiter_field_id'])
                ->first()
            : null;
        $managerField = isset($data['manager_field_id'])
            ? CrmCustomFieldSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->where('entity_type', 'leads')
                ->where('amo_field_id', (int) $data['manager_field_id'])
                ->first()
            : null;
        $teamField = isset($data['team_field_id'])
            ? CrmCustomFieldSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->where('entity_type', 'leads')
                ->where('amo_field_id', (int) $data['team_field_id'])
                ->first()
            : null;
        $cityField = isset($data['city_field_id'])
            ? CrmCustomFieldSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->where('entity_type', 'leads')
                ->where('amo_field_id', (int) $data['city_field_id'])
                ->first()
            : null;

        if (isset($data['pipeline_id']) && $pipeline === null) {
            throw ValidationException::withMessages(['pipeline_id' => 'Выберите воронку из списка синхронизированных воронок.']);
        }

        if (isset($data['recruiter_field_id']) && $field === null) {
            throw ValidationException::withMessages(['recruiter_field_id' => 'Выберите поле сделки из списка синхронизированных полей.']);
        }

        if (isset($data['manager_field_id']) && $managerField === null) {
            throw ValidationException::withMessages(['manager_field_id' => 'Выберите поле сделки из списка синхронизированных полей.']);
        }

        if (isset($data['team_field_id']) && $teamField === null) {
            throw ValidationException::withMessages(['team_field_id' => 'Выберите поле сделки из списка синхронизированных полей.']);
        }

        if (isset($data['city_field_id']) && $cityField === null) {
            throw ValidationException::withMessages(['city_field_id' => 'Выберите поле сделки из списка синхронизированных полей.']);
        }

        $installation = $this->installation($amoAccount, $dashboardWidget);
        $installation->forceFill([
            'config' => [
                'pipeline_id' => $pipeline?->amo_pipeline_id,
                'pipeline_name' => $pipeline?->name,
                'recruiter_field_id' => $field?->amo_field_id,
                'recruiter_field_name' => $field?->name,
                'manager_field_id' => $managerField?->amo_field_id,
                'manager_field_name' => $managerField?->name,
                'team_field_id' => $teamField?->amo_field_id,
                'team_field_name' => $teamField?->name,
                'city_field_id' => $cityField?->amo_field_id,
                'city_field_name' => $cityField?->name,
            ],
        ])->save();

        return redirect()
            ->route('amo-accounts.widgets.settings', [$amoAccount, $dashboardWidget])
            ->with('status', 'Настройки отчета сохранены.');
    }

    private function installation(AmoAccount $amoAccount, DashboardWidget $dashboardWidget): AmoAccountDashboardWidget
    {
        return AmoAccountDashboardWidget::query()->firstOrCreate(
            ['amo_account_id' => $amoAccount->id, 'dashboard_widget_id' => $dashboardWidget->id],
            ['public_key' => Str::random(48), 'is_enabled' => true]
        );
    }

    private function links(AmoAccount $amoAccount): array
    {
        return [
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
        ];
    }
}
