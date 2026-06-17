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

    public function index(Request $request, AmoAccount $amoAccount): Response
    {
        $status = $request->input('status');
        $overdue = $request->input('overdue');
        $responsibleUserId = $request->input('responsible_user_id');

        $query = CrmEntitySnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->where('entity_type', 'tasks')
            ->orderByDesc('entity_created_at');

        if ($status === 'open') {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw, '$.is_completed')) != 'true'");
        } elseif ($status === 'closed') {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw, '$.is_completed')) = 'true'");
        }

        if ($overdue === '1') {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw, '$.is_completed')) != 'true'")
                ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(raw, '$.complete_till')) AS UNSIGNED) > 0")
                ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(raw, '$.complete_till')) AS UNSIGNED) < UNIX_TIMESTAMP(NOW())");
        } elseif ($overdue === '0') {
            $query->where(fn ($q) => $q
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw, '$.is_completed')) = 'true'")
                ->orWhereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(raw, '$.complete_till')) AS UNSIGNED) = 0")
                ->orWhereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(raw, '$.complete_till')) AS UNSIGNED) >= UNIX_TIMESTAMP(NOW())")
            );
        }

        if ($responsibleUserId) {
            $query->where('responsible_user_id', (int) $responsibleUserId);
        }

        $userMap = $amoAccount->usersSnapshots()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['amo_user_id', 'name'])
            ->keyBy('amo_user_id');

        $now = now()->timestamp;

        return Inertia::render('AmoAccounts/Tasks/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'tasks' => $query->paginate(50)->withQueryString()->through(fn (CrmEntitySnapshot $task): array => [
                'id' => $task->id,
                'external_id' => $task->external_id,
                'text' => $task->raw['text'] ?? $task->name ?? '-',
                'responsible_user_id' => $task->responsible_user_id,
                'responsible_name' => $userMap->get($task->responsible_user_id)?->name,
                'deadline' => isset($task->raw['complete_till']) && $task->raw['complete_till'] > 0
                    ? \Illuminate\Support\Carbon::createFromTimestamp($task->raw['complete_till'])->format('d.m.Y H:i')
                    : null,
                'is_completed' => (bool) ($task->raw['is_completed'] ?? false),
                'is_overdue' => !($task->raw['is_completed'] ?? false)
                    && isset($task->raw['complete_till'])
                    && (int) $task->raw['complete_till'] > 0
                    && (int) $task->raw['complete_till'] < $now,
                'created_at' => $task->entity_created_at?->format('d.m.Y H:i'),
            ]),
            'users' => $userMap->values()->map(fn ($u): array => [
                'id' => $u->amo_user_id,
                'name' => $u->name,
            ]),
            'filters' => [
                'status' => $status ?? '',
                'overdue' => $overdue ?? '',
                'responsible_user_id' => $responsibleUserId ?? '',
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
                'tasks' => route('amo-accounts.task-statistics.index', $amoAccount),
                'events_sync' => route('amo-accounts.events-sync.index', $amoAccount),
                'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                'integrations' => route('amo-accounts.integrations', $amoAccount),
                'widgets' => route('amo-accounts.widgets', $amoAccount),
            ],
        ];
    }
}
