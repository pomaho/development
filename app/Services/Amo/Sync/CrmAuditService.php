<?php

namespace App\Services\Amo\Sync;

use App\Models\AmoAccount;
use App\Models\CrmCustomFieldSnapshot;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineSnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Services\Amo\Analytics\AmoTaskStatisticsService;
use App\Services\Amo\Client\AmoFallbackHttpClient;
use Illuminate\Support\Carbon;

class CrmAuditService
{
    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function syncAll(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, ?int $pipelineId = null): array
    {
        $structure = $this->syncStructure($account, $pipelineId);
        $data = $this->syncOperationalData($account, $from, $to, $pipelineId);
        $this->refreshDashboardCache($account);

        return array_merge($structure, $data);
    }

    public function syncStructure(AmoAccount $account, ?int $pipelineId = null): array
    {
        $syncedAt = now();
        $counts = [
            'pipelines' => 0,
            'statuses' => 0,
            'custom_fields' => 0,
            'loss_reasons' => 0,
            'sources' => 0,
            'catalogs' => 0,
        ];

        foreach ($this->fetchPipelines($account, $pipelineId) as $pipeline) {
            CrmPipelineSnapshot::query()->updateOrCreate(
                ['amo_account_id' => $account->id, 'amo_pipeline_id' => $pipeline['id']],
                [
                    'name' => $pipeline['name'] ?? '',
                    'sort' => $pipeline['sort'] ?? null,
                    'is_main' => (bool) ($pipeline['is_main'] ?? false),
                    'is_unsorted_on' => (bool) ($pipeline['is_unsorted_on'] ?? false),
                    'is_archive' => (bool) ($pipeline['is_archive'] ?? false),
                    'raw' => $pipeline,
                    'synced_at' => $syncedAt,
                ]
            );
            $counts['pipelines']++;

            foreach (($pipeline['_embedded']['statuses'] ?? []) as $status) {
                $this->savePipelineStatus($account, (int) $pipeline['id'], $status, $syncedAt);
                $counts['statuses']++;
            }
        }

        foreach (['leads', 'contacts', 'companies'] as $entityType) {
            foreach ($this->fetchPaginated($account, "/api/v4/{$entityType}/custom_fields", 'custom_fields') as $field) {
                $this->saveCustomField($account, $entityType, $field, $syncedAt);
                $counts['custom_fields']++;
            }
        }

        $counts['loss_reasons'] = $this->syncSimpleEntity($account, 'loss_reasons', '/api/v4/leads/loss_reasons', 'loss_reasons', $syncedAt);
        $counts['sources'] = $this->syncSimpleEntity($account, 'sources', '/api/v4/sources', 'sources', $syncedAt);
        $counts['catalogs'] = $this->syncSimpleEntity($account, 'catalogs', '/api/v4/catalogs', 'catalogs', $syncedAt);
        $this->refreshDashboardCache($account);

        return $counts;
    }

    public function syncOperationalData(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, ?int $pipelineId = null): array
    {
        $syncedAt = now();
        $periodQuery = $this->periodQuery($from, $to);
        $leadQuery = [
            'with' => 'contacts,loss_reason,source',
            ...$periodQuery,
            ...$this->pipelineQuery($pipelineId),
        ];

        if ($pipelineId !== null) {
            $leads = $this->syncSimpleEntity($account, 'leads', '/api/v4/leads', 'leads', $syncedAt, $leadQuery);

            if ($leads === 0) {
                $leads = $this->syncSimpleEntity(
                    $account,
                    'leads',
                    '/api/v4/leads',
                    'leads',
                    $syncedAt,
                    ['with' => 'contacts,loss_reason,source', ...$periodQuery],
                    fn (array $lead): bool => (int) ($lead['pipeline_id'] ?? 0) === $pipelineId
                );
            }

            return [
                'leads' => $leads,
            ];
        }

        return [
            'leads' => $this->syncSimpleEntity($account, 'leads', '/api/v4/leads', 'leads', $syncedAt, $leadQuery),
            ...$this->syncContacts($account, $from, $to),
            'events' => $this->syncSimpleEntity($account, 'events', '/api/v4/events', 'events', $syncedAt, $periodQuery),
            'tasks' => $this->syncSimpleEntity($account, 'tasks', '/api/v4/tasks', 'tasks', $syncedAt, $periodQuery),
            'unsorted' => $this->syncSimpleEntity($account, 'unsorted', '/api/v4/leads/unsorted', 'unsorted', $syncedAt, $periodQuery),
        ];
    }

    /**
     * Like syncOperationalData()'s leads branch, but filters by amoCRM's updated_at instead
     * of created_at — so leads that were already synced but later edited (e.g. the
     * "Менеджер" field was filled/changed) get re-fetched too, not just brand-new leads.
     * Used by recurring schedules that need to catch "all changes in the last N days",
     * not just "new leads in the last N days".
     */
    public function syncRecentlyUpdatedLeads(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, ?int $pipelineId = null): array
    {
        $syncedAt = now();
        $updatedQuery = $this->updatedPeriodQuery($from, $to);
        $leadQuery = [
            'with' => 'contacts,loss_reason,source',
            ...$updatedQuery,
            ...$this->pipelineQuery($pipelineId),
        ];

        $leads = $this->syncSimpleEntity($account, 'leads', '/api/v4/leads', 'leads', $syncedAt, $leadQuery);

        if ($leads === 0 && $pipelineId !== null) {
            $leads = $this->syncSimpleEntity(
                $account,
                'leads',
                '/api/v4/leads',
                'leads',
                $syncedAt,
                ['with' => 'contacts,loss_reason,source', ...$updatedQuery],
                fn (array $lead): bool => (int) ($lead['pipeline_id'] ?? 0) === $pipelineId
            );
        }

        return ['leads' => $leads];
    }

    public function syncContacts(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $syncedAt = now();
        $periodQuery = $this->periodQuery($from, $to);

        return [
            'contacts' => $this->syncSimpleEntity($account, 'contacts', '/api/v4/contacts', 'contacts', $syncedAt, [
                'with' => 'leads,companies',
                ...$periodQuery,
            ]),
            'companies' => $this->syncSimpleEntity($account, 'companies', '/api/v4/companies', 'companies', $syncedAt, [
                'with' => 'contacts,leads',
                ...$periodQuery,
            ]),
        ];
    }

    public function auditSummary(AmoAccount $account): array
    {
        return [
            'pipelines' => CrmPipelineSnapshot::query()->where('amo_account_id', $account->id)->count(),
            'statuses' => CrmPipelineStatusSnapshot::query()->where('amo_account_id', $account->id)->count(),
            'custom_fields' => CrmCustomFieldSnapshot::query()->where('amo_account_id', $account->id)->count(),
            'leads' => $this->entityCount($account, 'leads'),
            'contacts' => $this->entityCount($account, 'contacts'),
            'companies' => $this->entityCount($account, 'companies'),
            'events' => $this->entityCount($account, 'events'),
            'tasks' => $this->entityCount($account, 'tasks'),
            'unsorted' => $this->entityCount($account, 'unsorted'),
            'last_sync' => CrmEntitySnapshot::query()
                ->where('amo_account_id', $account->id)
                ->max('synced_at') ?: CrmPipelineSnapshot::query()->where('amo_account_id', $account->id)->max('synced_at'),
        ];
    }

    private function fetchPipelines(AmoAccount $account, ?int $onlyPipelineId = null): array
    {
        $pipelines = $this->fetchPaginated($account, '/api/v4/leads/pipelines', 'pipelines');

        return collect($pipelines)
            ->filter(fn (array $pipeline): bool => $onlyPipelineId === null || (int) ($pipeline['id'] ?? 0) === $onlyPipelineId)
            ->map(function (array $pipeline) use ($account): array {
                if (isset($pipeline['_embedded']['statuses'])) {
                    return $pipeline;
                }

                $statuses = $this->fetchPaginated($account, "/api/v4/leads/pipelines/{$pipeline['id']}/statuses", 'statuses');
                $pipeline['_embedded']['statuses'] = $statuses;

                return $pipeline;
            })->all();
    }

    private function fetchPaginated(AmoAccount $account, string $path, string $embeddedKey, array $query = []): array
    {
        $page = 1;
        $items = [];

        do {
            $payload = $this->http->get($account, $path, [...$query, 'page' => $page, 'limit' => 250]);
            $items = array_merge($items, $payload['_embedded'][$embeddedKey] ?? []);

            $currentPage = (int) ($payload['_page'] ?? $page);
            $pageCount = (int) ($payload['_page_count'] ?? $currentPage);
            $hasNext = isset($payload['_links']['next']);
            $page++;

            if ($hasNext) {
                usleep(160000);
            }
        } while ($hasNext || $currentPage < $pageCount);

        return $items;
    }

    private function savePipelineStatus(AmoAccount $account, int $pipelineId, array $status, Carbon $syncedAt): void
    {
        CrmPipelineStatusSnapshot::query()->updateOrCreate(
            ['amo_account_id' => $account->id, 'amo_pipeline_id' => $pipelineId, 'amo_status_id' => $status['id']],
            [
                'name' => $status['name'] ?? '',
                'sort' => $status['sort'] ?? null,
                'color' => $status['color'] ?? null,
                'type' => $status['type'] ?? null,
                'raw' => $status,
                'synced_at' => $syncedAt,
            ]
        );
    }

    private function saveCustomField(AmoAccount $account, string $entityType, array $field, Carbon $syncedAt): void
    {
        CrmCustomFieldSnapshot::query()->updateOrCreate(
            ['amo_account_id' => $account->id, 'entity_type' => $entityType, 'amo_field_id' => $field['id']],
            [
                'name' => $field['name'] ?? '',
                'field_type' => $field['type'] ?? null,
                'code' => $field['code'] ?? null,
                'group_id' => $field['group_id'] ?? null,
                'sort' => $field['sort'] ?? null,
                'is_required' => $field['is_required'] ?? null,
                'is_api_only' => $field['is_api_only'] ?? null,
                'enums' => $field['enums'] ?? null,
                'required_statuses' => $field['required_statuses'] ?? null,
                'raw' => $field,
                'synced_at' => $syncedAt,
            ]
        );
    }

    private function syncSimpleEntity(
        AmoAccount $account,
        string $entityType,
        string $path,
        string $embeddedKey,
        Carbon $syncedAt,
        array $query = [],
        ?callable $filter = null
    ): int {
        $count = 0;

        foreach ($this->fetchPaginated($account, $path, $embeddedKey, $query) as $entity) {
            if ($filter !== null && ! $filter($entity)) {
                continue;
            }

            CrmEntitySnapshot::query()->updateOrCreate(
                ['amo_account_id' => $account->id, 'entity_type' => $entityType, 'external_id' => (string) ($entity['id'] ?? md5(json_encode($entity)))],
                [
                    'name' => $entity['name'] ?? $entity['text'] ?? $entity['type'] ?? null,
                    'pipeline_id' => $entity['pipeline_id'] ?? null,
                    'status_id' => $entity['status_id'] ?? null,
                    'responsible_user_id' => $entity['responsible_user_id'] ?? null,
                    'entity_created_at' => $this->timestamp($entity['created_at'] ?? null),
                    'entity_updated_at' => $this->timestamp($entity['updated_at'] ?? null),
                    'entity_closed_at' => $this->timestamp($entity['closed_at'] ?? null),
                    'custom_fields_values' => $entity['custom_fields_values'] ?? null,
                    'embedded' => $entity['_embedded'] ?? null,
                    'raw' => $entity,
                    'synced_at' => $syncedAt,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function periodQuery(?Carbon $from, ?Carbon $to): array
    {
        return array_filter([
            'filter[created_at][from]' => $from?->timestamp,
            'filter[created_at][to]' => $to?->timestamp,
        ], fn ($value) => $value !== null);
    }

    private function pipelineQuery(?int $pipelineId): array
    {
        return $pipelineId ? ['filter[pipeline_id]' => $pipelineId] : [];
    }

    private function updatedPeriodQuery(?Carbon $from, ?Carbon $to): array
    {
        return array_filter([
            'filter[updated_at][from]' => $from?->timestamp,
            'filter[updated_at][to]' => $to?->timestamp,
        ], fn ($value) => $value !== null);
    }

    private function timestamp(mixed $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    private function refreshDashboardCache(AmoAccount $account): void
    {
        app(AmoTaskStatisticsService::class)->refreshDashboardCacheVersion($account);
    }

    private function entityCount(AmoAccount $account, string $entityType): int
    {
        return CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', $entityType)
            ->count();
    }
}
