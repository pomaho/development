<?php

namespace App\Services\Amo\Structure;

use App\Models\AmoAccount;
use App\Services\Amo\Client\AmoFallbackHttpClient;

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

    public function fetchCatalogElements(AmoAccount $account, int $catalogId): array
    {
        return $this->fetchPaginated($account, "/api/v4/catalogs/{$catalogId}/elements", 'elements');
    }

    public function previewComposedElementNames(AmoAccount $account, int $parentCatalogId, int $childCatalogId, string $template, array $manualMappings = []): array
    {
        $parents = collect($this->fetchCatalogElements($account, $parentCatalogId))
            ->filter(fn (array $element): bool => isset($element['id']))
            ->map(fn (array $element): array => [
                'id' => (int) $element['id'],
                'name' => $this->elementName($element),
                'raw' => $element,
            ])
            ->values();
        $children = collect($this->fetchCatalogElements($account, $childCatalogId))
            ->filter(fn (array $element): bool => isset($element['id']))
            ->map(fn (array $element): array => [
                'id' => (int) $element['id'],
                'name' => $this->elementName($element),
                'raw' => $element,
            ])
            ->values();
        $parentsById = $parents->keyBy('id');
        $parentsByName = $parents->keyBy(fn (array $parent): string => $this->normalizeName($parent['name']));
        $manualMap = $this->normalizeManualMappings($manualMappings);
        $existingNames = $children->map(fn (array $child): string => $this->normalizeName($child['name']))->all();
        $newNames = [];

        $rows = $children->map(function (array $child) use ($parentsById, $parentsByName, $manualMap, $template, $existingNames, &$newNames): array {
            $parent = $this->parentForChild($child, $parentsById, $parentsByName, $manualMap);

            if ($parent === null) {
                return [
                    'child_id' => $child['id'],
                    'old_name' => $child['name'],
                    'parent_id' => null,
                    'parent_name' => null,
                    'new_name' => null,
                    'status' => 'no_parent',
                    'message' => 'Не найден связанный проект.',
                ];
            }

            $baseChildName = $this->childNameWithoutParentPrefix($child['name'], $parent['name']);
            $newName = $this->composeName($template, $parent['name'], $baseChildName);
            $normalizedNewName = $this->normalizeName($newName);

            $row = [
                'child_id' => $child['id'],
                'old_name' => $child['name'],
                'parent_id' => $parent['id'],
                'parent_name' => $parent['name'],
                'new_name' => $newName,
                'status' => 'ready',
                'message' => 'Готово к переименованию.',
            ];

            if ($this->normalizeName($child['name']) === $normalizedNewName) {
                $row['status'] = 'unchanged';
                $row['message'] = 'Название уже соответствует шаблону.';
            } elseif (in_array($normalizedNewName, $existingNames, true) || in_array($normalizedNewName, $newNames, true)) {
                $row['status'] = 'duplicate_new_name';
                $row['message'] = 'Новое название дублируется в списке.';
            }

            $newNames[] = $normalizedNewName;

            return $row;
        })->values();

        return [
            'rows' => $rows->all(),
            'total' => $rows->count(),
            'ready' => $rows->where('status', 'ready')->count(),
            'unchanged' => $rows->where('status', 'unchanged')->count(),
            'skipped' => $rows->whereNotIn('status', ['ready', 'unchanged'])->count(),
        ];
    }

    public function applyComposedElementNames(AmoAccount $account, int $parentCatalogId, int $childCatalogId, string $template, array $manualMappings = []): array
    {
        $preview = $this->previewComposedElementNames($account, $parentCatalogId, $childCatalogId, $template, $manualMappings);
        $updates = collect($preview['rows'])
            ->filter(fn (array $row): bool => $row['status'] === 'ready' && filled($row['new_name'] ?? null))
            ->map(fn (array $row): array => [
                'id' => (int) $row['child_id'],
                'name' => (string) $row['new_name'],
            ])
            ->values();

        $updates->chunk(50)->each(function ($chunk) use ($account, $childCatalogId): void {
            if ($chunk->isNotEmpty()) {
                $this->http->patch($account, "/api/v4/catalogs/{$childCatalogId}/elements", $chunk->values()->all());
            }
        });

        return [
            ...$preview,
            'updated' => $updates->count(),
        ];
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

    private function parentForChild(array $child, mixed $parentsById, mixed $parentsByName, array $manualMap): ?array
    {
        $childKeys = [(string) $child['id'], $this->normalizeName($child['name'])];
        foreach ($childKeys as $key) {
            if (! isset($manualMap[$key])) {
                continue;
            }

            $manualParent = $manualMap[$key];

            if (is_numeric($manualParent) && $parentsById->has((int) $manualParent)) {
                return $parentsById->get((int) $manualParent);
            }

            $parentByName = $parentsByName->get($this->normalizeName((string) $manualParent));
            if ($parentByName !== null) {
                return $parentByName;
            }
        }

        $parentId = $this->extractParentElementId($child['raw'], $parentsById, $parentsByName);

        return $parentId !== null ? $parentsById->get($parentId) : null;
    }

    private function extractParentElementId(array $payload, mixed $parentsById, mixed $parentsByName): ?int
    {
        foreach (['parent_id', 'parent_element_id', 'parent_catalog_element_id'] as $key) {
            if (isset($payload[$key]) && $parentsById->has((int) $payload[$key])) {
                return (int) $payload[$key];
            }
        }

        foreach (['parent', 'parent_element', 'parent_catalog_element'] as $key) {
            if (is_array($payload[$key] ?? null)) {
                $parentId = $this->extractParentElementId($payload[$key], $parentsById, $parentsByName);
                if ($parentId !== null) {
                    return $parentId;
                }
            }
        }

        foreach (($payload['custom_fields_values'] ?? []) as $field) {
            foreach (($field['values'] ?? []) as $value) {
                $candidate = $value['catalog_element_id'] ?? $value['enum_id'] ?? $value['id'] ?? null;
                if ($candidate !== null && $parentsById->has((int) $candidate)) {
                    return (int) $candidate;
                }

                if (isset($value['value'])) {
                    $parent = $parentsByName->get($this->normalizeName((string) $value['value']));
                    if ($parent !== null) {
                        return (int) $parent['id'];
                    }
                }
            }
        }

        return null;
    }

    private function normalizeManualMappings(array $manualMappings): array
    {
        return collect($manualMappings)
            ->filter(fn (array $mapping): bool => filled($mapping['child'] ?? null) && filled($mapping['parent'] ?? null))
            ->flatMap(fn (array $mapping): array => [
                (string) $mapping['child'] => (string) $mapping['parent'],
                $this->normalizeName((string) $mapping['child']) => (string) $mapping['parent'],
            ])
            ->all();
    }

    private function elementName(array $element): string
    {
        return trim((string) ($element['name'] ?? $element['value'] ?? ''));
    }

    private function composeName(string $template, string $parentName, string $childName): string
    {
        $name = strtr($template ?: '{parent} {child}', [
            '{parent}' => $parentName,
            '{project}' => $parentName,
            '{child}' => $childName,
            '{subgroup}' => $childName,
        ]);

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function childNameWithoutParentPrefix(string $childName, string $parentName): string
    {
        $prefix = $this->normalizeName($parentName);
        $normalizedChild = $this->normalizeName($childName);

        if ($prefix !== '' && str_starts_with($normalizedChild, $prefix.' ')) {
            return trim(mb_substr($childName, mb_strlen($parentName)));
        }

        return $childName;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }
}
