<?php

namespace App\Services\Amo\Webhooks;

use App\Services\Amo\Client\AmoFallbackHttpClient;

use App\Models\AmoAccount;
use App\Models\AmoWebhookEvent;
use App\Models\CrmEntitySnapshot;
use App\Services\Amo\Analytics\AmoTaskStatisticsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use RuntimeException;

class AmoWebhookService
{
    private const ENTITY_MAP = [
        'leads' => [
            'path' => '/api/v4/leads',
            'with' => 'contacts,loss_reason,source',
        ],
        'contacts' => [
            'path' => '/api/v4/contacts',
            'with' => 'leads,companies',
        ],
        'companies' => [
            'path' => '/api/v4/companies',
            'with' => 'contacts,leads',
        ],
        'tasks' => [
            'path' => '/api/v4/tasks',
            'with' => null,
        ],
    ];

    private const PAYLOAD_ENTITY_ALIASES = [
        'task' => 'tasks',
    ];

    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function createEvents(AmoAccount $account, array $payload): array
    {
        $events = $this->extractEvents($payload);

        if ($events === []) {
            $events[] = [
                'event_type' => 'unknown',
                'entity_type' => null,
                'entity_id' => null,
                'payload' => $payload,
            ];
        }

        return array_map(fn (array $event): AmoWebhookEvent => $this->createOrRefreshPendingEvent($account, $event), $events);
    }

    private function createOrRefreshPendingEvent(AmoAccount $account, array $event): AmoWebhookEvent
    {
        $existing = $event['entity_type'] && $event['entity_id']
            ? AmoWebhookEvent::query()
                ->where('amo_account_id', $account->id)
                ->where('entity_type', $event['entity_type'])
                ->where('entity_id', $event['entity_id'])
                ->where('status', AmoWebhookEvent::STATUS_PENDING)
                ->latest('received_at')
                ->first()
            : null;

        if ($existing) {
            $existing->forceFill([
                'event_type' => $event['event_type'],
                'payload' => $event['payload'],
                'received_at' => now(),
                'error_message' => null,
            ])->save();

            return $existing;
        }

        return AmoWebhookEvent::query()->create([
            'amo_account_id' => $account->id,
            'event_type' => $event['event_type'],
            'entity_type' => $event['entity_type'],
            'entity_id' => $event['entity_id'],
            'payload' => $event['payload'],
            'status' => AmoWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);
    }

    public function process(AmoWebhookEvent $event): void
    {
        $event->loadMissing('account');

        if (! $event->entity_type || ! $event->entity_id || ! isset(self::ENTITY_MAP[$event->entity_type])) {
            $event->forceFill([
                'status' => AmoWebhookEvent::STATUS_SKIPPED,
                'processed_at' => now(),
                'error_message' => null,
            ])->save();

            return;
        }

        if (str_ends_with($event->event_type, '.delete')) {
            CrmEntitySnapshot::query()
                ->where('amo_account_id', $event->amo_account_id)
                ->where('entity_type', $event->entity_type)
                ->where('external_id', $event->entity_id)
                ->delete();

            $event->forceFill([
                'status' => AmoWebhookEvent::STATUS_PROCESSED,
                'processed_at' => now(),
                'error_message' => null,
            ])->save();

            return;
        }

        $entity = $this->fetchEntity($event->account, $event->entity_type, $event->entity_id);
        $this->saveEntitySnapshot($event->account, $event->entity_type, $entity);

        if ($event->entity_type === 'tasks') {
            $this->syncTaskEvents($event->account, $event->entity_id);
            app(AmoTaskStatisticsService::class)->refreshDashboardCacheVersion($event->account);
        }

        $event->forceFill([
            'status' => AmoWebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
            'error_message' => null,
        ])->save();
    }

    private function extractEvents(array $payload): array
    {
        $events = [];

        foreach ($this->payloadEntityTypes() as $payloadEntityType => $entityType) {
            $entityPayload = Arr::get($payload, $payloadEntityType);

            if (! is_array($entityPayload)) {
                continue;
            }

            foreach ($entityPayload as $action => $items) {
                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $entityId = $item['id'] ?? $item['entity_id'] ?? null;

                    if (! $entityId) {
                        continue;
                    }

                    $events[] = [
                        'event_type' => "{$entityType}.{$action}",
                        'entity_type' => $entityType,
                        'entity_id' => (string) $entityId,
                        'payload' => $item,
                    ];
                }
            }
        }

        return $events;
    }

    private function payloadEntityTypes(): array
    {
        $types = array_combine(array_keys(self::ENTITY_MAP), array_keys(self::ENTITY_MAP));

        return [...$types, ...self::PAYLOAD_ENTITY_ALIASES];
    }

    private function fetchEntity(AmoAccount $account, string $entityType, string $entityId): array
    {
        $config = self::ENTITY_MAP[$entityType] ?? null;

        if (! $config) {
            throw new RuntimeException("Unsupported amoCRM webhook entity type: {$entityType}");
        }

        $query = $config['with'] ? ['with' => $config['with']] : [];

        return $this->http->get($account, "{$config['path']}/{$entityId}", $query);
    }

    private function syncTaskEvents(AmoAccount $account, string $taskId): void
    {
        $page = 1;
        $completionStats = null;

        do {
            $payload = $this->http->get($account, '/api/v4/events', [
                'filter[entity][]' => 'task',
                'filter[entity_id]' => $taskId,
                'page' => $page,
                'limit' => 250,
            ]);
            $events = $payload['_embedded']['events'] ?? [];
            $events = is_array($events) ? $events : [];

            foreach ($events as $event) {
                $this->saveEventSnapshot($account, $event);

                if (($event['type'] ?? null) !== 'task_completed') {
                    continue;
                }

                $completedAt = (int) ($event['created_at'] ?? 0);
                $currentCompletedAt = (int) ($completionStats['completed_at'] ?? 0);

                if ($completedAt > 0 && ($currentCompletedAt === 0 || $completedAt < $currentCompletedAt)) {
                    $completionStats = $this->completionStatsFromEvent($event);
                }
            }

            $currentPage = (int) ($payload['_page'] ?? $page);
            $pageCount = (int) ($payload['_page_count'] ?? 0);
            $hasNext = isset($payload['_links']['next']['href']);
            $page++;

            if ($hasNext) {
                usleep(160000);
            }
        } while (($pageCount > 0 && $currentPage < $pageCount) || ($pageCount === 0 && $hasNext));

        if ($completionStats !== null) {
            $this->mergeTaskCompletionStats($account, $taskId, $completionStats);
        }
    }

    private function saveEntitySnapshot(AmoAccount $account, string $entityType, array $entity): void
    {
        CrmEntitySnapshot::query()->updateOrCreate(
            ['amo_account_id' => $account->id, 'entity_type' => $entityType, 'external_id' => (string) ($entity['id'] ?? md5(json_encode($entity)))],
            [
                'name' => $this->previewText($entity['name'] ?? $entity['text'] ?? $entity['type'] ?? null),
                'pipeline_id' => $entity['pipeline_id'] ?? null,
                'status_id' => $entity['status_id'] ?? null,
                'responsible_user_id' => $entity['responsible_user_id'] ?? null,
                'entity_created_at' => $this->timestamp($entity['created_at'] ?? null),
                'entity_updated_at' => $this->timestamp($entity['updated_at'] ?? null),
                'entity_closed_at' => $this->timestamp($entity['closed_at'] ?? null),
                'custom_fields_values' => $entity['custom_fields_values'] ?? null,
                'embedded' => $entity['_embedded'] ?? null,
                'raw' => $entity,
                'synced_at' => now(),
            ]
        );
    }

    private function saveEventSnapshot(AmoAccount $account, array $event): void
    {
        CrmEntitySnapshot::query()->updateOrCreate(
            ['amo_account_id' => $account->id, 'entity_type' => 'events', 'external_id' => (string) ($event['id'] ?? md5(json_encode($event)))],
            [
                'name' => $this->previewText($event['type'] ?? 'event'),
                'responsible_user_id' => $event['created_by'] ?? null,
                'entity_created_at' => $this->timestamp($event['created_at'] ?? null),
                'entity_updated_at' => $this->timestamp($event['created_at'] ?? null),
                'embedded' => [
                    'entity_id' => $event['entity_id'] ?? null,
                    'entity_type' => $event['entity_type'] ?? $event['entity'] ?? null,
                ],
                'raw' => $event,
                'synced_at' => now(),
            ]
        );
    }

    private function mergeTaskCompletionStats(AmoAccount $account, string $taskId, array $completionStats): void
    {
        $task = CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->where('external_id', $taskId)
            ->first();

        if (! $task) {
            return;
        }

        $raw = $task->raw ?? [];
        $raw['_task_statistics'] = $completionStats;

        $task->forceFill(['raw' => $raw])->save();
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
