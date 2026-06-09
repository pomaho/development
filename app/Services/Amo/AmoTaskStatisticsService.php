<?php

namespace App\Services\Amo;

use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Models\CrmEntitySnapshot;
use Illuminate\Support\Carbon;

class AmoTaskStatisticsService
{
    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function sync(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $syncedAt = now();
        $completed = $this->fetchTasks($account, [
            'filter[is_completed]' => 1,
            ...$this->updatedAtQuery($from, $to),
        ]);
        $open = $this->fetchTasks($account, [
            'filter[is_completed]' => 0,
        ]);

        foreach (array_merge($completed, $open) as $task) {
            $this->saveTask($account, $task, $syncedAt);
        }

        return [
            'completed' => count($completed),
            'open' => count($open),
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
                        'open_count' => 0,
                        'overdue_count' => 0,
                        'total_count' => 0,
                    ];

                    $isCompleted = (bool) ($raw['is_completed'] ?? false);
                    $completeTill = $this->timestamp($raw['complete_till'] ?? null);
                    $updatedAt = $task->entity_updated_at;

                    if ($isCompleted && $this->inPeriod($updatedAt, $from, $to)) {
                        $rows[$responsibleId]['completed_count']++;
                        $rows[$responsibleId]['total_count']++;
                    }

                    if (! $isCompleted) {
                        $rows[$responsibleId]['open_count']++;
                        $rows[$responsibleId]['total_count']++;

                        if ($completeTill !== null && $completeTill->lessThan($now)) {
                            $rows[$responsibleId]['overdue_count']++;
                        }
                    }
                }
            });

        return collect($rows)
            ->map(function (array $row): array {
                $row['overdue_rate'] = $row['open_count'] > 0
                    ? round($row['overdue_count'] / $row['open_count'] * 100, 1)
                    : 0.0;

                return $row;
            })
            ->sortByDesc('total_count')
            ->values()
            ->all();
    }

    private function fetchTasks(AmoAccount $account, array $query): array
    {
        $page = 1;
        $items = [];

        do {
            $payload = $this->http->get($account, '/api/v4/tasks', [...$query, 'page' => $page, 'limit' => 250]);
            $items = array_merge($items, $payload['_embedded']['tasks'] ?? []);

            $currentPage = (int) ($payload['_page'] ?? $page);
            $pageCount = (int) ($payload['_page_count'] ?? 0);
            $hasNext = isset($payload['_links']['next']['href']);
            $page++;

            if ($hasNext) {
                usleep(160000);
            }
        } while (($pageCount > 0 && $currentPage < $pageCount) || ($pageCount === 0 && $hasNext));

        return $items;
    }

    private function saveTask(AmoAccount $account, array $task, Carbon $syncedAt): void
    {
        CrmEntitySnapshot::query()->updateOrCreate(
            ['amo_account_id' => $account->id, 'entity_type' => 'tasks', 'external_id' => (string) $task['id']],
            [
                'name' => $task['text'] ?? null,
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

    private function updatedAtQuery(?Carbon $from, ?Carbon $to): array
    {
        return array_filter([
            'filter[updated_at][from]' => $from?->timestamp,
            'filter[updated_at][to]' => $to?->timestamp,
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
}
