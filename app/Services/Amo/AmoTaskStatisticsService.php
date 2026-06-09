<?php

namespace App\Services\Amo;

use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Models\CrmEntitySnapshot;
use App\Models\TaskStatisticsSyncRun;
use Illuminate\Support\Carbon;

class AmoTaskStatisticsService
{
    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function sync(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, ?TaskStatisticsSyncRun $run = null): array
    {
        $syncedAt = now();
        $run?->forceFill([
            'status' => TaskStatisticsSyncRun::STATUS_RUNNING,
            'started_at' => now(),
        ])->save();

        $completed = $this->syncTaskQuery($account, [
            'filter[is_completed]' => 1,
            ...$this->updatedAtQuery($from, $to),
        ], $syncedAt, $run, 'completed');
        $completionEvents = $this->syncCompletionEvents($account, $from, $to, $run);
        $open = $this->syncTaskQuery($account, [
            'filter[is_completed]' => 0,
        ], $syncedAt, $run, 'open');

        $run?->forceFill([
            'status' => TaskStatisticsSyncRun::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();

        return [
            'completed' => $completed,
            'completion_events' => $completionEvents,
            'open' => $open,
        ];
    }

    public function statistics(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $users = AmoUsersSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->get()
            ->keyBy('amo_user_id');
        $rows = [];
        $now = now();

        CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->orderBy('id')
            ->chunkById(500, function ($tasks) use (&$rows, $users, $from, $to, $now): void {
                foreach ($tasks as $task) {
                    $raw = $task->raw ?? [];
                    $responsibleId = (int) ($task->responsible_user_id ?? 0);

                    if ($responsibleId <= 0) {
                        continue;
                    }

                    $rows[$responsibleId] ??= [
                        'responsible_user_id' => $responsibleId,
                        'responsible_name' => $users->get($responsibleId)?->name,
                        'completed_count' => 0,
                        'completed_overdue_count' => 0,
                        'open_count' => 0,
                        'open_overdue_count' => 0,
                        'overdue_count' => 0,
                        'total_count' => 0,
                    ];

                    $isCompleted = (bool) ($raw['is_completed'] ?? false);
                    $completeTill = $this->timestamp($raw['complete_till'] ?? null);
                    $updatedAt = $task->entity_updated_at;
                    $completedAt = $this->completionTime($raw) ?? $updatedAt;
                    $completedLate = $isCompleted && $completeTill !== null && $completedAt !== null && $completedAt->greaterThan($completeTill);

                    if ($isCompleted && $this->inPeriod($completedAt, $from, $to)) {
                        $rows[$responsibleId]['completed_count']++;
                        $rows[$responsibleId]['total_count']++;

                        if ($completedLate) {
                            $rows[$responsibleId]['completed_overdue_count']++;
                            $rows[$responsibleId]['overdue_count']++;
                        }
                    }

                    if (! $isCompleted) {
                        $rows[$responsibleId]['open_count']++;
                        $rows[$responsibleId]['total_count']++;

                        if ($completeTill !== null && $completeTill->lessThan($now)) {
                            $rows[$responsibleId]['open_overdue_count']++;
                            $rows[$responsibleId]['overdue_count']++;
                        }
                    }
                }
            });

        return collect($rows)
            ->map(function (array $row): array {
                $row['overdue_rate'] = $row['total_count'] > 0
                    ? round($row['overdue_count'] / $row['total_count'] * 100, 1)
                    : 0.0;

                return $row;
            })
            ->sortByDesc('total_count')
            ->values()
            ->all();
    }

    private function syncTaskQuery(AmoAccount $account, array $query, Carbon $syncedAt, ?TaskStatisticsSyncRun $run, string $type): int
    {
        $page = 1;
        $total = 0;

        do {
            $payload = $this->http->get($account, '/api/v4/tasks', [...$query, 'page' => $page, 'limit' => 250]);
            $tasks = $payload['_embedded']['tasks'] ?? [];
            $tasks = is_array($tasks) ? $tasks : [];

            foreach ($tasks as $task) {
                $this->saveTask($account, $task, $syncedAt);
            }

            $count = count($tasks);
            $total += $count;
            $this->updateRunProgress($run, $type, $count);

            $currentPage = (int) ($payload['_page'] ?? $page);
            $pageCount = (int) ($payload['_page_count'] ?? 0);
            $hasNext = isset($payload['_links']['next']['href']);
            $page++;

            if ($hasNext) {
                usleep(160000);
            }
        } while (($pageCount > 0 && $currentPage < $pageCount) || ($pageCount === 0 && $hasNext));

        return $total;
    }

    private function syncCompletionEvents(AmoAccount $account, ?Carbon $from, ?Carbon $to, ?TaskStatisticsSyncRun $run): int
    {
        $page = 1;
        $total = 0;
        $eventStatsByTaskId = [];
        $query = [
            'filter[type][]' => 'task_completed',
            'filter[entity][]' => 'task',
            ...$this->createdAtQuery($from, $to),
        ];

        do {
            $payload = $this->http->get($account, '/api/v4/events', [...$query, 'page' => $page, 'limit' => 250]);
            $events = $payload['_embedded']['events'] ?? [];
            $events = is_array($events) ? $events : [];

            foreach ($events as $event) {
                $taskId = (int) ($event['entity_id'] ?? 0);

                if ($taskId <= 0) {
                    continue;
                }

                $eventCompletedAt = (int) ($event['created_at'] ?? 0);
                $currentCompletedAt = (int) ($eventStatsByTaskId[$taskId]['completed_at'] ?? 0);

                if ($eventCompletedAt > 0 && ($currentCompletedAt === 0 || $eventCompletedAt < $currentCompletedAt)) {
                    $eventStatsByTaskId[$taskId] = $this->completionStatsFromEvent($event);
                }
            }

            $count = count($events);
            $total += $count;
            $this->updateRunProgress($run, 'completion_events', $count);

            $currentPage = (int) ($payload['_page'] ?? $page);
            $pageCount = (int) ($payload['_page_count'] ?? 0);
            $hasNext = isset($payload['_links']['next']['href']);
            $page++;

            if ($hasNext) {
                usleep(160000);
            }
        } while (($pageCount > 0 && $currentPage < $pageCount) || ($pageCount === 0 && $hasNext));

        $this->syncTasksByIds($account, $eventStatsByTaskId, now());

        return $total;
    }

    private function syncTasksByIds(AmoAccount $account, array $eventStatsByTaskId, Carbon $syncedAt): void
    {
        foreach (array_chunk($eventStatsByTaskId, 250, true) as $statsChunk) {
            if ($statsChunk === []) {
                continue;
            }

            $payload = $this->http->get($account, '/api/v4/tasks', [
                'filter[id]' => array_keys($statsChunk),
                'page' => 1,
                'limit' => 250,
            ]);
            $tasks = $payload['_embedded']['tasks'] ?? [];
            $tasks = is_array($tasks) ? $tasks : [];

            foreach ($tasks as $task) {
                $taskId = (int) ($task['id'] ?? 0);

                if (isset($statsChunk[$taskId])) {
                    $task['_task_statistics'] = $statsChunk[$taskId];
                }

                $this->saveTask($account, $task, $syncedAt);
            }
        }
    }

    private function updateRunProgress(?TaskStatisticsSyncRun $run, string $type, int $count): void
    {
        if ($run === null || $count === 0) {
            return;
        }

        $foundColumn = "{$type}_found";
        $syncedColumn = "{$type}_synced";
        $run->increment($foundColumn, $count);
        $run->increment($syncedColumn, $count);
        $run->refresh();
    }

    private function saveTask(AmoAccount $account, array $task, Carbon $syncedAt): void
    {
        $existing = CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->where('external_id', (string) $task['id'])
            ->first();
        $existingStats = $existing?->raw['_task_statistics'] ?? null;

        if ($existingStats !== null) {
            $task['_task_statistics'] = $existingStats;
        }

        CrmEntitySnapshot::query()->updateOrCreate(
            ['amo_account_id' => $account->id, 'entity_type' => 'tasks', 'external_id' => (string) $task['id']],
            [
                'name' => $this->previewText($task['text'] ?? null),
                'responsible_user_id' => $task['responsible_user_id'] ?? null,
                'entity_created_at' => $this->timestamp($task['created_at'] ?? null),
                'entity_updated_at' => $this->timestamp($task['updated_at'] ?? null),
                'embedded' => [
                    'entity_id' => $task['entity_id'] ?? null,
                    'entity_type' => $task['entity_type'] ?? null,
                ],
                'raw' => $task,
                'synced_at' => $syncedAt,
            ]
        );
    }

    private function completionStatsFromEvent(array $event): array
    {
        return [
            'completed_at' => (int) ($event['created_at'] ?? 0),
            'completed_by' => $event['created_by'] ?? null,
            'completed_event_id' => $event['id'] ?? null,
            'completed_event' => $event,
        ];
    }

    private function updatedAtQuery(?Carbon $from, ?Carbon $to): array
    {
        return array_filter([
            'filter[updated_at][from]' => $from?->timestamp,
            'filter[updated_at][to]' => $to?->timestamp,
        ], fn ($value) => $value !== null);
    }

    private function createdAtQuery(?Carbon $from, ?Carbon $to): array
    {
        return array_filter([
            'filter[created_at][from]' => $from?->timestamp,
            'filter[created_at][to]' => $to?->timestamp,
        ], fn ($value) => $value !== null);
    }

    private function inPeriod(?Carbon $date, ?Carbon $from, ?Carbon $to): bool
    {
        if ($date === null) {
            return false;
        }

        if ($from !== null && $date->lt($from)) {
            return false;
        }

        if ($to !== null && $date->gt($to)) {
            return false;
        }

        return true;
    }

    private function timestamp(mixed $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    private function completionTime(array $raw): ?Carbon
    {
        return $this->timestamp(data_get($raw, '_task_statistics.completed_at'));
    }

    private function previewText(mixed $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?: '');

        return mb_strlen($text) > 250 ? mb_substr($text, 0, 247).'...' : $text;
    }
}
