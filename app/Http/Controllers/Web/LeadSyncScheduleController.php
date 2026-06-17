<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadSyncScheduleRequest;
use App\Http\Requests\UpdateLeadSyncScheduleRequest;
use App\Jobs\SyncAmoTaskStatisticsJob;
use App\Models\AmoAccount;
use App\Models\CrmPipelineSnapshot;
use App\Models\LeadSyncSchedule;
use App\Models\TaskStatisticsSyncRun;
use App\Services\Amo\Sync\LeadSyncScheduleRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadSyncScheduleController extends Controller
{
    private const INTERVALS = [
        15 => 'Каждые 15 минут',
        30 => 'Каждые 30 минут',
        60 => 'Каждый час',
        180 => 'Каждые 3 часа',
        360 => 'Каждые 6 часов',
        720 => 'Каждые 12 часов',
        1440 => 'Раз в день',
    ];

    private const ENTITY_LABELS = [
        LeadSyncSchedule::ENTITY_TYPE_LEADS => 'Сделки',
        LeadSyncSchedule::ENTITY_TYPE_TASKS => 'Задачи',
        LeadSyncSchedule::ENTITY_TYPE_EVENTS => 'События',
    ];

    public function index(AmoAccount $amoAccount): Response
    {
        $this->authorize('view', $amoAccount);

        $pipelines = CrmPipelineSnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->orderBy('is_archive')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return Inertia::render('AmoAccounts/LeadSyncSchedules/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'can' => [
                'manage' => request()->user()?->can('sync', $amoAccount) ?? false,
            ],
            'intervals' => collect(self::INTERVALS)->map(fn (string $label, int $minutes): array => [
                'minutes' => $minutes,
                'label' => $label,
            ])->values(),
            'entityTypes' => collect(self::ENTITY_LABELS)->map(fn (string $label, string $type): array => [
                'type' => $type,
                'label' => $label,
            ])->values(),
            'pipelines' => $pipelines->map(fn (CrmPipelineSnapshot $pipeline): array => [
                'amo_pipeline_id' => $pipeline->amo_pipeline_id,
                'name' => $pipeline->name,
                'is_archive' => $pipeline->is_archive,
            ]),
            'schedules' => LeadSyncSchedule::query()
                ->where('amo_account_id', $amoAccount->id)
                ->orderByDesc('is_enabled')
                ->orderBy('entity_type')
                ->orderBy('pipeline_name')
                ->get()
                ->map(fn (LeadSyncSchedule $schedule): array => [
                    'id' => $schedule->id,
                    'entity_type' => $schedule->entity_type,
                    'entity_label' => self::ENTITY_LABELS[$schedule->entity_type] ?? $schedule->entity_type,
                    'amo_pipeline_id' => $schedule->amo_pipeline_id,
                    'pipeline_name' => $schedule->pipeline_name,
                    'interval_minutes' => $schedule->interval_minutes,
                    'interval_label' => self::INTERVALS[$schedule->interval_minutes] ?? "{$schedule->interval_minutes} мин.",
                    'lookback_days' => $schedule->lookback_days,
                    'is_enabled' => $schedule->is_enabled,
                    'last_run_at' => $schedule->last_run_at?->toDateTimeString(),
                    'last_finished_at' => $schedule->last_finished_at?->toDateTimeString(),
                    'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
                    'last_status' => $schedule->last_status,
                    'last_synced_count' => $schedule->last_synced_count,
                    'last_error' => $schedule->last_error,
                ]),
            'defaults' => [
                'interval_minutes' => 60,
                'lookback_days' => 45,
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'store' => route('amo-accounts.lead-sync-schedules.store', $amoAccount),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'lead_sync_schedules' => route('amo-accounts.lead-sync-schedules.index', $amoAccount),
                    'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                ],
            ],
        ]);
    }

    public function store(StoreLeadSyncScheduleRequest $request, AmoAccount $amoAccount): RedirectResponse
    {
        $data = $request->validated();
        $entityType = $data['entity_type'];

        $payload = [
            'amo_account_id' => $amoAccount->id,
            'entity_type' => $entityType,
            'interval_minutes' => (int) $data['interval_minutes'],
            'lookback_days' => (int) $data['lookback_days'],
            'is_enabled' => $request->boolean('is_enabled'),
            'next_run_at' => now(),
        ];

        if ($entityType === LeadSyncSchedule::ENTITY_TYPE_LEADS) {
            $pipeline = $this->pipeline($amoAccount, (int) $data['amo_pipeline_id']);
            $payload['amo_pipeline_id'] = $pipeline->amo_pipeline_id;
            $payload['pipeline_name'] = $pipeline->name;
        }

        LeadSyncSchedule::query()->create($payload);

        $label = self::ENTITY_LABELS[$entityType] ?? $entityType;

        return back()->with('status', "Расписание синхронизации ({$label}) добавлено.");
    }

    public function update(UpdateLeadSyncScheduleRequest $request, AmoAccount $amoAccount, LeadSyncSchedule $leadSyncSchedule): RedirectResponse
    {
        $this->abortIfWrongAccount($amoAccount, $leadSyncSchedule);

        $data = $request->validated();
        $entityType = $leadSyncSchedule->entity_type;

        $payload = [
            'interval_minutes' => (int) $data['interval_minutes'],
            'lookback_days' => (int) $data['lookback_days'],
            'is_enabled' => $request->boolean('is_enabled'),
            'next_run_at' => $request->boolean('is_enabled')
                ? ($leadSyncSchedule->next_run_at ?? now())
                : null,
        ];

        if ($entityType === LeadSyncSchedule::ENTITY_TYPE_LEADS) {
            $pipeline = $this->pipeline($amoAccount, (int) $data['amo_pipeline_id']);
            $payload['amo_pipeline_id'] = $pipeline->amo_pipeline_id;
            $payload['pipeline_name'] = $pipeline->name;
        }

        $leadSyncSchedule->update($payload);

        $label = self::ENTITY_LABELS[$entityType] ?? $entityType;

        return back()->with('status', "Расписание синхронизации ({$label}) обновлено.");
    }

    public function run(Request $request, AmoAccount $amoAccount, LeadSyncSchedule $leadSyncSchedule, LeadSyncScheduleRunner $runner): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);
        $this->abortIfWrongAccount($amoAccount, $leadSyncSchedule);

        $data = $request->validate([
            'lookback_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        if ($leadSyncSchedule->entity_type === LeadSyncSchedule::ENTITY_TYPE_LEADS) {
            $syncedCount = $runner->run($leadSyncSchedule->load('account'), (int) $data['lookback_days'], false);

            return back()->with('status', "Разовая синхронизация завершена. Загружено сделок: {$syncedCount}.");
        }

        $run = $this->dispatchNonLeadsSync($amoAccount, (int) $data['lookback_days']);

        return back()->with('status', "Синхронизация поставлена в очередь. Запуск #{$run->id}.");
    }

    public function destroy(AmoAccount $amoAccount, LeadSyncSchedule $leadSyncSchedule): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);
        $this->abortIfWrongAccount($amoAccount, $leadSyncSchedule);

        $leadSyncSchedule->delete();

        $label = self::ENTITY_LABELS[$leadSyncSchedule->entity_type] ?? $leadSyncSchedule->entity_type;

        return back()->with('status', "Расписание синхронизации ({$label}) удалено.");
    }

    private function dispatchNonLeadsSync(AmoAccount $amoAccount, int $lookbackDays): TaskStatisticsSyncRun
    {
        $to = now()->endOfDay();
        $from = $to->copy()->subDays($lookbackDays - 1)->startOfDay();

        $run = TaskStatisticsSyncRun::query()->create([
            'amo_account_id' => $amoAccount->id,
            'status' => TaskStatisticsSyncRun::STATUS_PENDING,
            'period_from' => $from,
            'period_to' => $to,
        ]);

        SyncAmoTaskStatisticsJob::dispatch($run->id);

        return $run;
    }

    private function pipeline(AmoAccount $amoAccount, int $pipelineId): CrmPipelineSnapshot
    {
        return CrmPipelineSnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->where('amo_pipeline_id', $pipelineId)
            ->firstOrFail();
    }

    private function abortIfWrongAccount(AmoAccount $amoAccount, LeadSyncSchedule $schedule): void
    {
        abort_unless($schedule->amo_account_id === $amoAccount->id, 404);
    }
}
