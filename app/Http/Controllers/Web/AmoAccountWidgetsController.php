<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWidgetSettingsRequest;
use App\Models\AmoAccount;
use App\Models\AmoAccountDashboardWidget;
use App\Models\CrmCustomFieldSnapshot;
use App\Models\CrmPipelineSnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Models\DashboardWidget;
use App\Services\Amo\Analytics\AmoTaskStatisticsService;
use Illuminate\Http\RedirectResponse;
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
                            'iframe_url' => match ($widget->code) {
                                'task_overdue_dashboard' => route('widgets.amo.task-overdue-dashboard.show', $installation->public_key),
                                'task_overdue_dashboard_v2' => route('widgets.amo.task-overdue-dashboard-v2.show', $installation->public_key),
                                'task_overdue_dashboard_v2_dev' => route('widgets.amo.task-overdue-dashboard-v2-dev.show', $installation->public_key),
                                'manager_topup_dashboard' => route('widgets.amo.manager-topup.show', $installation->public_key),
                                default => null,
                            },
                            'api_url' => in_array($widget->code, ['task_overdue_dashboard', 'task_overdue_dashboard_v2'])
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
        $rawConfig = $installation->config ?? [];

        $pipelines = CrmPipelineSnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (CrmPipelineSnapshot $pipeline): array => [
                'id' => $pipeline->amo_pipeline_id,
                'name' => $pipeline->name,
                'is_archive' => $pipeline->is_archive,
            ]);

        $leadFields = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->where('entity_type', 'leads')
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (CrmCustomFieldSnapshot $field): array => [
                'id' => $field->amo_field_id,
                'name' => $field->name,
                'field_type' => $field->field_type,
            ]);

        $isTopupWidget = $dashboardWidget->code === 'manager_topup_dashboard';

        if ($isTopupWidget) {
            $config = [
                'pipeline_id' => data_get($rawConfig, 'pipeline_id'),
                'manager_field_id' => data_get($rawConfig, 'manager_field_id'),
                'prepayment_field_id' => data_get($rawConfig, 'prepayment_field_id'),
                'topup_date_field_id' => data_get($rawConfig, 'topup_date_field_id'),
            ];

            return Inertia::render('AmoAccounts/Widgets/Settings', [
                'account' => ['id' => $amoAccount->id, 'name' => $amoAccount->name, 'base_domain' => $amoAccount->base_domain],
                'widget' => ['id' => $dashboardWidget->id, 'code' => $dashboardWidget->code, 'name' => $dashboardWidget->name],
                'config' => $config,
                'diagnostics' => null,
                'pipelineStatuses' => [],
                'pipelines' => $pipelines,
                'leadFields' => $leadFields,
                'links' => $this->links($amoAccount) + [
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                    'save' => route('amo-accounts.widgets.settings.update', [$amoAccount, $dashboardWidget]),
                    'crm_fields' => route('amo-accounts.crm-audit.fields', $amoAccount),
                ],
            ]);
        }

        $config = [
            'pipeline_id' => data_get($rawConfig, 'pipeline_id'),
            'pipeline_name' => data_get($rawConfig, 'pipeline_name'),
            'recruiter_field_id' => data_get($rawConfig, 'recruiter_field_id'),
            'recruiter_field_name' => data_get($rawConfig, 'recruiter_field_name'),
            'manager_field_id' => data_get($rawConfig, 'manager_field_id'),
            'manager_field_name' => data_get($rawConfig, 'manager_field_name'),
            'team_field_id' => data_get($rawConfig, 'team_field_id'),
            'team_field_name' => data_get($rawConfig, 'team_field_name'),
            'city_field_id' => data_get($rawConfig, 'city_field_id'),
            'city_field_name' => data_get($rawConfig, 'city_field_name'),
            'source_field_id' => data_get($rawConfig, 'source_field_id'),
            'source_field_name' => data_get($rawConfig, 'source_field_name'),
            'success_status_id' => data_get($rawConfig, 'success_status_id'),
            'success_status_name' => data_get($rawConfig, 'success_status_name'),
        ];

        return Inertia::render('AmoAccounts/Widgets/Settings', [
            'account' => ['id' => $amoAccount->id, 'name' => $amoAccount->name, 'base_domain' => $amoAccount->base_domain],
            'widget' => ['id' => $dashboardWidget->id, 'code' => $dashboardWidget->code, 'name' => $dashboardWidget->name],
            'config' => [
                'pipeline_id' => $config['pipeline_id'],
                'recruiter_field_id' => $config['recruiter_field_id'],
                'manager_field_id' => $config['manager_field_id'],
                'team_field_id' => $config['team_field_id'],
                'city_field_id' => $config['city_field_id'],
                'source_field_id' => $config['source_field_id'],
                'success_status_id' => $config['success_status_id'],
            ],
            'diagnostics' => $statisticsService->recruiterLeadDistributionDiagnostics($amoAccount, null, null, $config),
            'pipelineStatuses' => CrmPipelineStatusSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->when($config['pipeline_id'], fn ($q) => $q->where('amo_pipeline_id', (int) $config['pipeline_id']))
                ->orderBy('sort')
                ->get()
                ->map(fn (CrmPipelineStatusSnapshot $status): array => [
                    'id' => $status->amo_status_id,
                    'name' => $status->name,
                    'pipeline_id' => $status->amo_pipeline_id,
                ]),
            'pipelines' => $pipelines,
            'leadFields' => $leadFields,
            'links' => $this->links($amoAccount) + [
                'widgets' => route('amo-accounts.widgets', $amoAccount),
                'save' => route('amo-accounts.widgets.settings.update', [$amoAccount, $dashboardWidget]),
                'crm_fields' => route('amo-accounts.crm-audit.fields', $amoAccount),
            ],
        ]);
    }

    public function updateSettings(UpdateWidgetSettingsRequest $request, AmoAccount $amoAccount, DashboardWidget $dashboardWidget): RedirectResponse
    {
        $data = $request->validated();

        $pipeline = isset($data['pipeline_id'])
            ? CrmPipelineSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->where('amo_pipeline_id', (int) $data['pipeline_id'])
                ->first()
            : null;

        if (isset($data['pipeline_id']) && $pipeline === null) {
            throw ValidationException::withMessages(['pipeline_id' => 'Выберите воронку из списка синхронизированных воронок.']);
        }

        $installation = $this->installation($amoAccount, $dashboardWidget);

        if ($dashboardWidget->code === 'manager_topup_dashboard') {
            $installation->forceFill([
                'config' => $this->buildTopupConfig($amoAccount, $data, $pipeline),
            ])->save();
        } else {
            $installation->forceFill([
                'config' => $this->buildRecruiterConfig($amoAccount, $data, $pipeline),
            ])->save();
        }

        return redirect()
            ->route('amo-accounts.widgets.settings', [$amoAccount, $dashboardWidget])
            ->with('status', 'Настройки отчета сохранены.');
    }

    private function buildTopupConfig(AmoAccount $amoAccount, array $data, ?CrmPipelineSnapshot $pipeline): array
    {
        $managerField = $this->resolveLeadField($amoAccount, $data['manager_field_id'] ?? null, 'manager_field_id');
        $prepaymentField = $this->resolveLeadField($amoAccount, $data['prepayment_field_id'] ?? null, 'prepayment_field_id');
        $topupDateField = $this->resolveLeadField($amoAccount, $data['topup_date_field_id'] ?? null, 'topup_date_field_id');

        return [
            'pipeline_id' => $pipeline?->amo_pipeline_id,
            'pipeline_name' => $pipeline?->name,
            'manager_field_id' => $managerField?->amo_field_id,
            'manager_field_name' => $managerField?->name,
            'prepayment_field_id' => $prepaymentField?->amo_field_id,
            'prepayment_field_name' => $prepaymentField?->name,
            'topup_date_field_id' => $topupDateField?->amo_field_id,
            'topup_date_field_name' => $topupDateField?->name,
        ];
    }

    private function buildRecruiterConfig(AmoAccount $amoAccount, array $data, ?CrmPipelineSnapshot $pipeline): array
    {
        $recruiterField = $this->resolveLeadField($amoAccount, $data['recruiter_field_id'] ?? null, 'recruiter_field_id');
        $managerField = $this->resolveLeadField($amoAccount, $data['manager_field_id'] ?? null, 'manager_field_id');
        $teamField = $this->resolveLeadField($amoAccount, $data['team_field_id'] ?? null, 'team_field_id');
        $cityField = $this->resolveLeadField($amoAccount, $data['city_field_id'] ?? null, 'city_field_id');
        $sourceField = $this->resolveLeadField($amoAccount, $data['source_field_id'] ?? null, 'source_field_id');
        $successStatus = isset($data['success_status_id'])
            ? CrmPipelineStatusSnapshot::query()
                ->where('amo_account_id', $amoAccount->id)
                ->where('amo_status_id', (int) $data['success_status_id'])
                ->first()
            : null;

        return [
            'pipeline_id' => $pipeline?->amo_pipeline_id,
            'pipeline_name' => $pipeline?->name,
            'recruiter_field_id' => $recruiterField?->amo_field_id,
            'recruiter_field_name' => $recruiterField?->name,
            'manager_field_id' => $managerField?->amo_field_id,
            'manager_field_name' => $managerField?->name,
            'team_field_id' => $teamField?->amo_field_id,
            'team_field_name' => $teamField?->name,
            'city_field_id' => $cityField?->amo_field_id,
            'city_field_name' => $cityField?->name,
            'source_field_id' => $sourceField?->amo_field_id,
            'source_field_name' => $sourceField?->name,
            'success_status_id' => $successStatus?->amo_status_id,
            'success_status_name' => $successStatus?->name,
        ];
    }

    private function resolveLeadField(AmoAccount $amoAccount, mixed $fieldId, string $formKey): ?CrmCustomFieldSnapshot
    {
        if (empty($fieldId)) {
            return null;
        }

        $field = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->where('entity_type', 'leads')
            ->where('amo_field_id', (int) $fieldId)
            ->first();

        if ($field === null) {
            throw ValidationException::withMessages([$formKey => 'Выберите поле сделки из списка синхронизированных полей.']);
        }

        return $field;
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
