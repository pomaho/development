<?php

namespace App\Services\Amo;

use App\Services\Amo\Client\AmoFallbackHttpClient;

use App\Models\AmoAccount;

class AmoCatalogsService
{
    private const CUSTOM_FIELD_ENTITIES = ['leads', 'contacts', 'companies'];

    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function fetchCatalogs(AmoAccount $account): array
    {
        return $this->fetchPaginated($account, '/api/v4/catalogs', 'catalogs');
    }

    public function createCatalog(AmoAccount $account, array $data): array
    {
        return $this->http->post($account, '/api/v4/catalogs', [[
            'name' => $data['name'],
            'type' => $data['type'] ?? 'regular',
            'sort' => (int) ($data['sort'] ?? 10),
            'can_add_elements' => (bool) ($data['can_add_elements'] ?? true),
            'can_show_in_cards' => (bool) ($data['can_show_in_cards'] ?? true),
            'can_link_multiple' => (bool) ($data['can_link_multiple'] ?? true),
        ]]);
    }

    public function createElements(AmoAccount $account, int $catalogId, array $names): array
    {
        $payload = collect($names)
            ->filter(fn (string $name): bool => filled($name))
            ->map(fn (string $name): array => ['name' => $name])
            ->values()
            ->all();

        return $this->http->post($account, "/api/v4/catalogs/{$catalogId}/elements", $payload);
    }

    public function createChainedListField(AmoAccount $account, array $data): array
    {
        $entity = $data['entity_type'] === 'customers' ? 'customers' : 'leads';
        $levels = collect($data['levels'] ?? [])
            ->filter(fn (array $level): bool => filled($level['title'] ?? null) && filled($level['catalog_id'] ?? null))
            ->map(fn (array $level, int $index): array => [
                'title' => $level['title'],
                'catalog_id' => (int) $level['catalog_id'],
                'parent_catalog_id' => $index === 0 ? 0 : (int) ($level['parent_catalog_id'] ?? 0),
            ])
            ->values()
            ->all();

        return $this->http->post($account, "/api/v4/{$entity}/custom_fields", [[
            'name' => $data['name'],
            'type' => 'chained_list',
            'sort' => (int) ($data['sort'] ?? 100),
            'chained_lists' => $levels,
        ]]);
    }

    public function fetchEnumCustomFields(AmoAccount $account): array
    {
        $fields = [];

        foreach (self::CUSTOM_FIELD_ENTITIES as $entity) {
            foreach ($this->fetchPaginated($account, "/api/v4/{$entity}/custom_fields", 'custom_fields') as $field) {
                if (! is_array($field['enums'] ?? null) || $field['enums'] === []) {
                    continue;
                }

                $field['entity_type'] = $entity;
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function updateEnumCustomField(AmoAccount $account, string $entityType, int $fieldId, array $data): array
    {
        $entity = in_array($entityType, self::CUSTOM_FIELD_ENTITIES, true) ? $entityType : 'leads';

        return $this->http->patch($account, "/api/v4/{$entity}/custom_fields/{$fieldId}", [
            'name' => $data['name'],
            'enums' => collect($data['enums'] ?? [])
                ->filter(fn (array $enum): bool => filled($enum['value'] ?? null))
                ->map(function (array $enum, int $index): array {
                    $payload = [
                        'value' => $enum['value'],
                        'sort' => $index,
                    ];

                    if (isset($enum['id']) && (int) $enum['id'] > 0) {
                        $payload['id'] = (int) $enum['id'];
                    }

                    return $payload;
                })
                ->values()
                ->all(),
        ]);
    }

    private function fetchPaginated(AmoAccount $account, string $path, string $embeddedKey, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->http->get($account, $path, array_merge($query, [
                'page' => $page,
                'limit' => 250,
            ]));

            $items = array_merge($items, $response['_embedded'][$embeddedKey] ?? []);
            $pageCount = (int) ($response['_page_count'] ?? $page);
            $page++;
        } while ($page <= $pageCount);

        return $items;
    }
}
