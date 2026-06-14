<?php

namespace App\Services\Amo\Webhooks;

use App\Services\Amo\Client\AmoFallbackHttpClient;

use App\Models\AmoAccount;
use App\Models\AmoWebhookEvent;
use App\Models\CrmEntitySnapshot;
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

        $event->forceFill([
            'status' => AmoWebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
            'error_message' => null,
        ])->save();
    }

    private function extractEvents(array $payload): array
    {
        $events = [];

        foreach (array_keys(self::ENTITY_MAP) as $entityType) {
            $entityPayload = Arr::get($payload, $entityType);

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

    private function fetchEntity(AmoAccount $account, string $entityType, string $entityId): array
    {
        $config = self::ENTITY_MAP[$entityType] ?? null;

        if (! $config) {
            throw new RuntimeException("Unsupported amoCRM webhook entity type: {$entityType}");
        }

        $query = $config['with'] ? ['with' => $config['with']] : [];

        return $this->http->get($account, "{$config['path']}/{$entityId}", $query);
    }

    private function saveEntitySnapshot(AmoAccount $account, string $entityType, array $entity): void
    {
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
                'synced_at' => now(),
            ]
        );
    }

    private function timestamp(mixed $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}
