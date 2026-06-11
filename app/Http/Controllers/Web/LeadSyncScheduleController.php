<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\CrmPipelineSnapshot;
use App\Models\LeadSyncSchedule;
use App\Services\Amo\LeadSyncScheduleRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'pipelines' => $pipelines->map(fn (CrmPipelineSnapshot $pipeline): array => [
                'amo_pipeline_id' => $pipeline->amo_pipeline_id,
                'name' => $pipeline->name,
                'is_archive' => $pipeline->is_archive,
            ]),
            'schedules' => LeadSyncSchedule::query()
                ->where('amo_account_id', $amoAccount->id)
                ->orderByDesc('is_enabled')
                ->orderBy('pipeline_name')
                ->get()
                ->map(fn (LeadSyncSchedule $schedule): array => [
                    'id' => $schedule->id,
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

    public function store(Request $request, AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $this->validated($request, $amoAccount);
        $pipeline = $this->pipeline($amoAccount, (int) $data['amo_pipeline_id']);

        LeadSyncSchedule::query()->create([
            'amo_account_id' => $amoAccount->id,
            'amo_pipeline_id' => $pipeline->amo_pipeline_id,
            'pipeline_name' => $pipeline->name,
            'interval_minutes' => (int) $data['interval_minutes'],
            'lookback_days' => (int) $data['lookback_days'],
            'is_enabled' => $request->boolean('is_enabled'),
            'next_run_at' => now(),
        ]);

        return back()->with('status', 'Расписание синхронизации сделок добавлено.');
    }

    public function update(Request $request, AmoAccount $amoAccount, LeadSyncSchedule $leadSyncSchedule): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);
        $this->abortIfWrongAccount($amoAccount, $leadSyncSchedule);

        $data = $this->validated($request, $amoAccount, $leadSyncSchedule);
        $pipeline = $this->pipeline($amoAccount, (int) $data['amo_pipeline_id']);

        $leadSyncSchedule->update([
            'amo_pipeline_id' => $pipeline->amo_pipeline_id,
            'pipeline_name' => $pipeline->name,
            'interval_minutes' => (int) $data['interval_minutes'],
            'lookback_days' => (int) $data['lookback_days'],
            'is_enabled' => $request->boolean('is_enabled'),
            'next_run_at' => $request->boolean('is_enabled')
                ? ($leadSyncSchedule->next_run_at ?? now())
                : null,
        ]);

        return back()->with('status', 'Расписание синхронизации сделок обновлено.');
    }

    public function run(Request $request, AmoAccount $amoAccount, LeadSyncSchedule $leadSyncSchedule, LeadSyncScheduleRunner $runner): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);
        $this->abortIfWrongAccount($amoAccount, $leadSyncSchedule);

        $data = $request->validate([
            'lookback_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $syncedCount = $runner->run($leadSyncSchedule->load('account'), (int) $data['lookback_days'], false);

        return back()->with('status', "Разовая синхронизация завершена. Загружено сделок: {$syncedCount}.");
    }

    public function destroy(AmoAccount $amoAccount, LeadSyncSchedule $leadSyncSchedule): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);
        $this->abortIfWrongAccount($amoAccount, $leadSyncSchedule);

        $leadSyncSchedule->delete();

        return back()->with('status', 'Расписание синхронизации сделок удалено.');
    }

    private function validated(Request $request, AmoAccount $amoAccount, ?LeadSyncSchedule $schedule = null): array
    {
        return $request->validate([
            'amo_pipeline_id' => [
                'required',
                'integer',
                Rule::exists('crm_pipelines_snapshots', 'amo_pipeline_id')->where('amo_account_id', $amoAccount->id),
                Rule::unique('lead_sync_schedules', 'amo_pipeline_id')
                    ->where('amo_account_id', $amoAccount->id)
                    ->ignore($schedule?->id),
            ],
            'interval_minutes' => ['required', 'integer', Rule::in(array_keys(self::INTERVALS))],
            'lookback_days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
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
