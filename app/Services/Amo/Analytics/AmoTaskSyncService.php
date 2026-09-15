<?php

namespace App\Services\Amo\Analytics;

use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\TaskStatisticsSyncRun;
use App\Services\Amo\Client\AmoFallbackHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AmoTaskSyncService
{
    /**
     * Event types actually consumed anywhere downstream (lead-status funnel reports,
     * via raw->value_before/value_after) — every other event type amoCRM emits
     * (field changes, tags, chat messages, ...) is requested and stored nowhere else
     * in the app, so there's no reason to pull or persist it here. Extend this list
     * if a future report needs another event type's payload.
     */
    private const SYNCED_EVENT_TYPES = ['lead_status_changed'];

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
        $completionEvents = $this->syncCompletionEvents($account, $from, $to, $run, $syncedAt);
        $openIds = [];
        $open = $this->syncTaskQuery($account, [
            'filter[is_completed]' => 0,
        ], $syncedAt, $run, 'open', $openIds);
        $this->reconcileMissingOpenTasks($account, $openIds);
        $events = $this->syncEvents($account, $from, $to, $syncedAt);

        $run?->forceFill([
            'status' => TaskStatisticsSyncRun::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();

        // $open is deliberately excluded: it's the full current open-task list on every
        // run (no date filter), not an incremental count, so it's always > 0 and would
        // defeat this check entirely. $completed/$completionEvents/$events are each
        // bound to the sync window, so a nonzero count here means something actually
        // changed — bumping the version otherwise (as this used to, unconditionally)
        // invalidates every cached report on every run, forcing a full recompute on
        // whichever report the next visitor happens to open, however cheap the run
        // itself was. Matters far more now that this runs hourly instead of every 6h.
        if ($completed + $completionEvents + $events > 0) {
            $this->statisticsService->refreshDashboardCacheVersion($account);
        }

        return [
            'completed' => $completed,
            'completion_events' => $completionEvents,
            'open' => $open,
            'events' => $events,
        ];
    }

    private function syncTaskQuery(AmoAccount $account, array $query, Carbon $syncedAt, ?TaskStatisticsSyncRun $run, string $type, array &$collectedIds = []): int
    {
        $page = 1;
        $total = 0;

        do {
            $payload = $this->getWithRetry($account, '/api/v4/tasks', [...$query, 'page' => $page, 'limit' => 250]);
            $tasks = $payload['_embedded']['tasks'] ?? [];
            $tasks = is_array($tasks) ? $tasks : [];

            foreach ($tasks as $task) {
                $this->saveTask($account, $task, $syncedAt);
                $collectedIds[] = (string) $task['id'];
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

    /**
     * The open-tasks fetch above has no date filter, so it's always the complete,
     * authoritative list of currently-open task ids. Any task still marked open
     * in our snapshot but absent from that list is gone in amoCRM — completed
     * without a matching completion event, or deleted outright (this app has no
     * webhook coverage for every possible cause of disappearance) — so it no
     * longer belongs in "open"/"overdue" counts. Delete it, matching how
     * AmoWebhookService::process() already handles explicit *.delete webhooks.
     */
    private function reconcileMissingOpenTasks(AmoAccount $account, array $openIds): void
    {
        if ($openIds === []) {
            Log::warning('Skipping task reconciliation: open-tasks fetch returned zero ids.', [
                'amo_account_id' => $account->id,
            ]);

            return;
        }

        // Chunk both the read and the delete instead of a single whereNotIn($openIds) —
        // on a busy account $openIds can run into the tens of thousands, which risks a
        // very large SQL IN-list; comparing against a PHP set is cheap either way.
        $openIdSet = array_flip($openIds);
        $missingIds = [];

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->whereRaw("JSON_EXTRACT(raw, '$.is_completed') = false")
            ->orderBy('id')
            ->chunkById(500, function ($tasks) use (&$missingIds, $openIdSet): void {
                foreach ($tasks as $task) {
                    if (!isset($openIdSet[$task->external_id])) {
                        $missingIds[] = $task->external_id;
                    }
                }
            });

        foreach (array_chunk($missingIds, 500) as $chunk) {
            CrmEntitySnapshot::query()
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'tasks')
                ->whereIn('external_id', $chunk)
                ->delete();
        }
    }

    private function syncCompletionEvents(AmoAccount $account, ?Carbon $from, ?Carbon $to, ?TaskStatisticsSyncRun $run, Carbon $syncedAt): int
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
            $payload = $this->getWithRetry($account, '/api/v4/events', [...$query, 'page' => $page, 'limit' => 250]);
            $events = $payload['_embedded']['events'] ?? [];
            $events = is_array($events) ? $events : [];

            foreach ($events as $event) {
                $this->saveEvent($account, $event, $syncedAt);

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

            $payload = $this->getWithRetry($account, '/api/v4/tasks', [
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

    /**
     * Public (rather than a private step of sync() only) so a one-off backfill for a
     * wide date range — e.g. repairing rows written with the empty-raw bug — can target
     * just events, without re-pulling every completed/open task over that same window.
     */
    public function syncEvents(AmoAccount $account, ?Carbon $from, ?Carbon $to, Carbon $syncedAt): int
    {
        $page = 1;
        $total = 0;
        // amoCRM rejects a PHP-array query value outright (400 "Invalid params passed
        // to filter"), so each type needs its own indexed key rather than filter[type][].
        $typeFilter = [];
        foreach (self::SYNCED_EVENT_TYPES as $index => $type) {
            $typeFilter["filter[type][{$index}]"] = $type;
        }
        $query = [...$this->createdAtQuery($from, $to), ...$typeFilter];

        do {
            $payload = $this->getWithRetry($account, '/api/v4/events', [...$query, 'page' => $page, 'limit' => 250]);
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

    /**
     * Wraps a single page fetch with a few retries on transient connection
     * timeouts. Full syncs page through thousands of requests (events in
     * particular — a busy account can have 1000+ pages for a wide date
     * range), so an occasional network blip shouldn't abort the whole run
     * and lose all prior progress.
     */
    private function getWithRetry(AmoAccount $account, string $path, array $query, int $attempts = 3): array
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->http->get($account, $path, $query);
            } catch (ConnectionException|RuntimeException $exception) {
                // RuntimeException also covers AmoRateLimitException (429) and amoCRM's
                // transient 5xx responses, not just connection-level timeouts — those are
                // exactly the failures a long paginated sync needs to survive.
                if ($attempt >= $attempts) {
                    throw $exception;
                }

                Log::warning('Retrying amoCRM API call after transient error.', [
                    'amo_account_id' => $account->id,
                    'path' => $path,
                    'attempt' => $attempt,
                    'exception' => $exception::class,
                ]);

                usleep(1_000_000 * $attempt);
            }
        }

        return [];
    }

    private function saveTask(AmoAccount $account, array $task, Carbon $syncedAt): void
    {
        $existing = CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->where('external_id', (string) $task['id'])
            ->first();

        $taskStats = $task['_task_statistics'] ?? $existing?->raw['_task_statistics'] ?? null;

        $taskRaw = [
            'is_completed' => (bool) ($task['is_completed'] ?? false),
            'complete_till' => $task['complete_till'] ?? null,
            'text' => $task['text'] ?? null,
            'result' => $task['result']['text'] ?? null,
        ];

        if ($taskStats !== null) {
            $taskRaw['_task_statistics'] = $taskStats;
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
                'raw' => $taskRaw,
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
                // Was hardcoded to [] (see git history: 24009d3, to satisfy this column's
                // NOT NULL constraint) — but that silently discarded every event's actual
                // payload, including value_before/value_after, which lead-status funnel
                // reports read straight out of this column. Save the real thing instead.
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
