<?php

namespace App\Services\Amo\Analytics;

use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\TaskStatisticsSyncRun;
use App\Services\Amo\Client\AmoFallbackHttpClient;
use Illuminate\Support\Carbon;

class AmoTaskSyncService
{
    public function __construct(
        private readonly AmoFallbackHttpClient $http,
        private readonly AmoTaskStatisticsService $statisticsService,
    ) {
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
        $events = $this->syncEvents($account, $from, $to, $syncedAt);

        $run?->forceFill([
            'status' => TaskStatisticsSyncRun::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();
        $this->statisticsService->refreshDashboardCacheVersion($account);

        return [
            'completed' => $completed,
            'completion_events' => $completionEvents,
            'open' => $open,
            'events' => $events,
        ];
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

            usleep(160000);
        }
    }

    private function syncEvents(AmoAccount $account, ?Carbon $from, ?Carbon $to, Carbon $syncedAt): int
    {
        $page = 1;
        $total = 0;
        $query = $this->createdAtQuery($from, $to);

        do {
            $payload = $this->http->get($account, '/api/v4/events', [...$query, 'page' => $page, 'limit' => 250]);
            $events = $payload['_embedded']['events'] ?? [];
            $events = is_array($events) ? $events : [];

            foreach ($events as $event) {
                $this->saveEvent($account, $event, $syncedAt);
            }

            $count = count($events);
            $total += $count;

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

    private function saveEvent(AmoAccount $account, array $event, Carbon $syncedAt): void
    {
        CrmEntitySnapshot::query()->updateOrCreate(
            ['amo_account_id' => $account->id, 'entity_type' => 'events', 'external_id' => (string) ($event['id'] ?? md5(json_encode($event)))],
            [
                'name' => $event['type'] ?? 'event',
                'responsible_user_id' => $event['created_by'] ?? null,
                'entity_created_at' => $this->timestamp($event['created_at'] ?? null),
                'entity_updated_at' => $this->timestamp($event['created_at'] ?? null),
                'embedded' => [
                    'entity_id' => $event['entity_id'] ?? null,
                    'entity_type' => $event['entity_type'] ?? $event['entity'] ?? null,
                ],
                'raw' => $event,
                'synced_at' => $syncedAt,
            ]
        );
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

    private function previewText(mixed $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?: '');

        return mb_strlen($text) > 250 ? mb_substr($text, 0, 247).'...' : $text;
    }

    private function timestamp(mixed $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}
