<?php

namespace App\Services\Amo;

use App\Models\AmoAccount;
use InvalidArgumentException;

class AmoResponsibilityRedistributionService
{
    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function activeUsers(AmoAccount $account): array
    {
        return collect($this->fetchPaged($account, '/api/v4/users', 'users', ['with' => 'role,group']))
            ->filter(fn (array $user): bool => $this->isActiveUser($user))
            ->map(fn (array $user): array => [
                'id' => (int) $user['id'],
                'name' => (string) ($user['name'] ?? 'Без имени'),
                'email' => $user['email'] ?? null,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function preview(AmoAccount $account, int $sourceUserId, array $targetUserIds): array
    {
        $targetUserIds = $this->normalizeTargets($sourceUserId, $targetUserIds);
        $contacts = $this->contactsByResponsible($account, $sourceUserId);
        $assignments = $this->buildAssignments($contacts, $targetUserIds);

        return [
            'source_user_id' => $sourceUserId,
            'target_user_ids' => $targetUserIds,
            'contacts_count' => count($contacts),
            'leads_count' => count($assignments['lead_target_map']),
            'by_target' => array_values($assignments['summary']),
            'sample_contacts' => array_slice(array_map(fn (array $contact): array => [
                'id' => (int) $contact['id'],
                'name' => $contact['name'] ?? 'Без имени',
                'lead_ids' => $this->leadIdsFromContact($contact),
            ], $contacts), 0, 20),
        ];
    }

    public function redistribute(AmoAccount $account, int $sourceUserId, array $targetUserIds): array
    {
        $targetUserIds = $this->normalizeTargets($sourceUserId, $targetUserIds);
        $contacts = $this->contactsByResponsible($account, $sourceUserId);
        $assignments = $this->buildAssignments($contacts, $targetUserIds);

        $updatedContacts = $this->patchResponsible($account, '/api/v4/contacts', $assignments['contact_target_map']);
        $updatedLeads = $this->patchResponsible($account, '/api/v4/leads', $assignments['lead_target_map']);

        $remainingContactIds = array_map(
            fn (array $contact): int => (int) $contact['id'],
            $this->contactsByResponsible($account, $sourceUserId)
        );
        $remainingLeadIds = array_map(
            fn (array $lead): int => (int) $lead['id'],
            $this->leadsByResponsible($account, $sourceUserId)
        );

        return [
            'source_user_id' => $sourceUserId,
            'target_user_ids' => $targetUserIds,
            'updated_contacts' => $updatedContacts,
            'updated_leads' => $updatedLeads,
            'remaining_contacts_count' => count($remainingContactIds),
            'remaining_leads_count' => count($remainingLeadIds),
            'remaining_contact_ids' => array_slice($remainingContactIds, 0, 50),
            'remaining_lead_ids' => array_slice($remainingLeadIds, 0, 50),
            'by_target' => array_values($assignments['summary']),
        ];
    }

    private function contactsByResponsible(AmoAccount $account, int $responsibleUserId): array
    {
        return $this->fetchPaged($account, '/api/v4/contacts', 'contacts', [
            'filter[responsible_user_id]' => $responsibleUserId,
            'with' => 'leads',
        ]);
    }

    private function leadsByResponsible(AmoAccount $account, int $responsibleUserId): array
    {
        return $this->fetchPaged($account, '/api/v4/leads', 'leads', [
            'filter[responsible_user_id]' => $responsibleUserId,
        ]);
    }

    private function fetchPaged(AmoAccount $account, string $path, string $embeddedKey, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->http->get($account, $path, $query + ['page' => $page, 'limit' => 250]);
            $pageItems = $response['_embedded'][$embeddedKey] ?? [];
            $items = array_merge($items, is_array($pageItems) ? $pageItems : []);

            $pageCount = (int) ($response['_page_count'] ?? $response['page_count'] ?? $page);
            $page++;
        } while ($page <= $pageCount);

        return $items;
    }

    private function buildAssignments(array $contacts, array $targetUserIds): array
    {
        $contactTargetMap = [];
        $leadTargetMap = [];
        $summary = collect($targetUserIds)
            ->mapWithKeys(fn (int $targetUserId): array => [$targetUserId => [
                'target_user_id' => $targetUserId,
                'contacts_count' => 0,
                'leads_count' => 0,
            ]])
            ->all();

        foreach (array_values($contacts) as $index => $contact) {
            $targetUserId = $targetUserIds[$index % count($targetUserIds)];
            $contactId = (int) $contact['id'];

            $contactTargetMap[$contactId] = $targetUserId;
            $summary[$targetUserId]['contacts_count']++;

            foreach ($this->leadIdsFromContact($contact) as $leadId) {
                if (isset($leadTargetMap[$leadId])) {
                    continue;
                }

                $leadTargetMap[$leadId] = $targetUserId;
                $summary[$targetUserId]['leads_count']++;
            }
        }

        return [
            'contact_target_map' => $contactTargetMap,
            'lead_target_map' => $leadTargetMap,
            'summary' => $summary,
        ];
    }

    private function patchResponsible(AmoAccount $account, string $path, array $targetMap): int
    {
        $updated = 0;

        foreach (array_chunk($targetMap, 250, true) as $chunk) {
            $payload = [];
            foreach ($chunk as $entityId => $targetUserId) {
                $payload[] = [
                    'id' => (int) $entityId,
                    'responsible_user_id' => (int) $targetUserId,
                ];
            }

            if ($payload === []) {
                continue;
            }

            $this->http->patch($account, $path, $payload);
            $updated += count($payload);
        }

        return $updated;
    }

    private function normalizeTargets(int $sourceUserId, array $targetUserIds): array
    {
        $targetUserIds = collect($targetUserIds)
            ->map(fn ($targetUserId): int => (int) $targetUserId)
            ->filter(fn (int $targetUserId): bool => $targetUserId > 0 && $targetUserId !== $sourceUserId)
            ->unique()
            ->values()
            ->all();

        if ($targetUserIds === []) {
            throw new InvalidArgumentException('Нужно выбрать хотя бы одного нового ответственного.');
        }

        return $targetUserIds;
    }

    private function leadIdsFromContact(array $contact): array
    {
        return collect($contact['_embedded']['leads'] ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function isActiveUser(array $user): bool
    {
        if (array_key_exists('is_active', $user)) {
            return (bool) $user['is_active'];
        }

        return (bool) data_get($user, 'rights.is_active', true);
    }
}
