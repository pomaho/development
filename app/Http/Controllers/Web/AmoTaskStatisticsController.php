<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SyncAmoTaskStatisticsJob;
use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\TaskStatisticsSyncRun;
use App\Services\Amo\Analytics\AmoTaskStatisticsService;
use App\Services\Exports\TableExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoTaskStatisticsController extends Controller
{
    private const SYNC_PERIOD_LIMIT_DAYS = 45;

    public function index(Request $request, AmoAccount $amoAccount, AmoTaskStatisticsService $statisticsService): Response
    {
        [$from, $to] = $this->period($request);

        return Inertia::render('AmoAccounts/TaskStatistics/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'rows' => $statisticsService->statistics($amoAccount, $from, $to),
            'runs' => $amoAccount->taskStatisticsSyncRuns()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (TaskStatisticsSyncRun $run): array => [
                    'id' => $run->id,
                    'status' => $run->status,
                    'period_from' => $run->period_from?->toDateString(),
                    'period_to' => $run->period_to?->toDateString(),
                    'completed_found' => $run->completed_found,
                    'completed_synced' => $run->completed_synced,
                    'completion_events_found' => $run->completion_events_found,
                    'completion_events_synced' => $run->completion_events_synced,
                    'open_found' => $run->open_found,
                    'open_synced' => $run->open_synced,
                    'error_message' => $run->error_message,
                    'created_at' => $run->created_at?->format('Y-m-d H:i:s'),
                    'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
                ]),
            'filters' => [
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
            ],
            'can' => [
                'sync' => $request->user()?->can('sync', $amoAccount) ?? false,
            ],
            'links' => $this->links($amoAccount, $request),
        ]);
    }

    public function sync(Request $request, AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        [$from, $to] = $this->syncPeriod($request);
        $run = TaskStatisticsSyncRun::query()->create([
            'amo_account_id' => $amoAccount->id,
            'status' => TaskStatisticsSyncRun::STATUS_PENDING,
            'period_from' => $from,
            'period_to' => $to,
        ]);

        SyncAmoTaskStatisticsJob::dispatch($run->id);

        return redirect()
            ->route('amo-accounts.task-statistics.index', array_merge(['amo_account' => $amoAccount], $request->only(['from', 'to'])))
            ->with('status', "Синхронизация задач поставлена в очередь. Запуск #{$run->id}.");
    }

    public function export(Request $request, AmoAccount $amoAccount, AmoTaskStatisticsService $statisticsService, TableExportService $export): StreamedResponse
    {
        [$from, $to] = $this->period($request);

        return $export->csv("amo-task-statistics-{$amoAccount->id}.csv", [
            'ID пользователя',
            'Пользователь',
            'Закрыто задач за период',
        ], collect($statisticsService->statistics($amoAccount, $from, $to))->map(fn (array $row): array => [
            $row['responsible_user_id'],
            $row['responsible_name'],
            $row['completed_count'],
        ]));
    }

    public function events(Request $request, AmoAccount $amoAccount): Response
    {
        $eventsQuery = CrmEntitySnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->where('entity_type', 'events');

        return Inertia::render('AmoAccounts/EventsSync/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'coverage' => [
                'events_count' => (clone $eventsQuery)->count(),
                'period_from' => (clone $eventsQuery)->min('entity_created_at'),
                'period_to' => (clone $eventsQuery)->max('entity_created_at'),
                'last_synced_at' => (clone $eventsQuery)->max('synced_at'),
                'cursor' => $amoAccount->taskStatisticsLastSuccessfulSyncAt()?->format('Y-m-d H:i:s'),
            ],
            'reportSettings' => [
                'avito_recruiting_group_id' => data_get($amoAccount->settings, 'reports.avito_recruiting_group_id'),
            ],
            'groups' => $amoAccount->usersSnapshots()
                ->whereNotNull('group_id')
                ->get()
                ->groupBy('group_id')
                ->map(fn ($users, $groupId): array => [
                    'id' => (int) $groupId,
                    'name' => data_get($users->first()?->raw, '_embedded.group.name')
                        ?: data_get($users->first()?->raw, 'group.name')
                        ?: "Группа {$groupId}",
                    'users_count' => $users->count(),
                ])
                ->sortBy('name')
                ->values()
                ->all(),
            'runs' => $amoAccount->taskStatisticsSyncRuns()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (TaskStatisticsSyncRun $run): array => [
                    'id' => $run->id,
                    'status' => $run->status,
                    'period_from' => $run->period_from?->toDateString(),
                    'period_to' => $run->period_to?->toDateString(),
                    'created_at' => $run->created_at?->format('Y-m-d H:i:s'),
                    'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
                    'error_message' => $run->error_message,
                ]),
            'can' => [
                'sync' => $request->user()?->can('sync', $amoAccount) ?? false,
            ],
            'links' => $this->links($amoAccount, $request),
        ]);
    }

    public function updateEventSettings(Request $request, AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('update', $amoAccount);

        $data = $request->validate([
            'avito_recruiting_group_id' => ['nullable', 'integer'],
            'avito_recruiting_group_id_manual' => ['nullable', 'integer'],
        ]);
        $groupId = $data['avito_recruiting_group_id_manual']
            ?: ($data['avito_recruiting_group_id'] ?? null);
        $settings = $amoAccount->settings ?? [];
        data_set($settings, 'reports.avito_recruiting_group_id', $groupId ?: null);

        $amoAccount->forceFill(['settings' => $settings])->save();
        $statisticsService = app(AmoTaskStatisticsService::class);
        $statisticsService->refreshDashboardCacheVersion($amoAccount);

        return redirect()
            ->route('amo-accounts.events-sync.index', $amoAccount)
            ->with('status', 'Настройки отчета сохранены.');
    }

    public function syncEvents(Request $request, AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $to = now()->endOfDay();
        $from = $to->copy()->subDays(self::SYNC_PERIOD_LIMIT_DAYS - 1)->startOfDay();
        $run = TaskStatisticsSyncRun::query()->create([
            'amo_account_id' => $amoAccount->id,
            'status' => TaskStatisticsSyncRun::STATUS_PENDING,
            'period_from' => $from,
            'period_to' => $to,
        ]);

        SyncAmoTaskStatisticsJob::dispatch($run->id);

        return redirect()
            ->route('amo-accounts.events-sync.index', $amoAccount)
            ->with('status', "Синхронизация событий за 45 дней поставлена в очередь. Запуск #{$run->id}.");
    }

    private function period(Request $request): array
    {
        return [
            $request->filled('from') ? $request->date('from')->startOfDay() : now()->startOfMonth(),
            $request->filled('to') ? $request->date('to')->endOfDay() : now()->endOfDay(),
        ];
    }

    private function syncPeriod(Request $request): array
    {
        [$from, $to] = $this->period($request);
        $minFrom = $to->copy()->subDays(self::SYNC_PERIOD_LIMIT_DAYS - 1)->startOfDay();

        if ($from->lt($minFrom)) {
            $from = $minFrom;
        }

        return [$from, $to];
    }

    private function links(AmoAccount $amoAccount, Request $request): array
    {
        return [
            'dashboard' => route('dashboard'),
            'amo_accounts' => route('amo-accounts.index'),
            'oauth' => route('amo-oauth.external.index'),
            'api_logs' => route('logs.api'),
            'logout' => route('logout'),
            'sync' => route('amo-accounts.task-statistics.sync', $amoAccount),
            'events_sync' => route('amo-accounts.events-sync.index', $amoAccount),
            'events_sync_start' => route('amo-accounts.events-sync.sync', $amoAccount),
            'events_sync_settings' => route('amo-accounts.events-sync.settings', $amoAccount),
            'export' => route('amo-accounts.task-statistics.export', array_merge(['amo_account' => $amoAccount], $request->query())),
            'reset' => route('amo-accounts.task-statistics.index', $amoAccount),
            'current_account' => [
                'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                'show' => route('amo-accounts.show', $amoAccount),
                'users' => route('amo-accounts.users', $amoAccount),
                'roles' => route('amo-accounts.roles', $amoAccount),
                'leads' => route('amo-accounts.leads', $amoAccount),
                'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                'catalogs' => route('amo-accounts.catalogs.index', $amoAccount),
                'responsibility_redistribution' => route('amo-accounts.responsibility-redistribution.index', $amoAccount),
                'task_statistics' => route('amo-accounts.task-statistics.index', $amoAccount),
                'events_sync' => route('amo-accounts.events-sync.index', $amoAccount),
                'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                'integrations' => route('amo-accounts.integrations', $amoAccount),
                'widgets' => route('amo-accounts.widgets', $amoAccount),
            ],
        ];
    }
}
