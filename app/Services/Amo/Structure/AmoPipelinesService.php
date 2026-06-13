<?php

namespace App\Services\Amo\Structure;

use App\Models\AmoAccount;
use App\Services\Amo\Client\AmoFallbackHttpClient;
use Throwable;

class AmoPipelinesService
{
    private const DEFAULT_STATUS_COLOR = '#98cbff';

    private const ALLOWED_STATUS_COLORS = [
        '#fffeb2',
        '#fffd7f',
        '#fff000',
        '#ffeab2',
        '#ffdc7f',
        '#ffce5a',
        '#ffdbdb',
        '#ffc8c8',
        '#ff8f92',
        '#d6eaff',
        '#c1e0ff',
        '#98cbff',
        '#ebffb1',
        '#deff81',
        '#87f2c0',
        '#f9deff',
        '#f3beff',
        '#ccc8f9',
        '#eb93ff',
        '#f2f3f4',
        '#e6e8ea',
    ];

    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function fetchPipelines(AmoAccount $account): array
    {
        $pipelines = $this->fetchPaginated($account, '/api/v4/leads/pipelines', 'pipelines', ['with' => 'statuses']);

        return collect($pipelines)
            ->map(function (array $pipeline) use ($account): array {
                if (isset($pipeline['_embedded']['statuses'])) {
                    return $pipeline;
                }

                $pipeline['_embedded']['statuses'] = $this->fetchPaginated(
                    $account,
                    "/api/v4/leads/pipelines/{$pipeline['id']}/statuses",
                    'statuses'
                );

                return $pipeline;
            })
            ->all();
    }

    public function fetchPipelineDetails(AmoAccount $account, int $pipelineId): array
    {
        $errors = [];
        $pipeline = $this->optionalGet($account, "/api/v4/leads/pipelines/{$pipelineId}", ['with' => 'statuses'], 'pipeline', $errors);

        if ($pipeline === []) {
            $pipeline = collect($this->fetchPipelines($account))
                ->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $pipelineId) ?? [];
        }

        $statuses = $this->optionalPaginated(
            $account,
            "/api/v4/leads/pipelines/{$pipelineId}/statuses",
            'statuses',
            ['with' => 'descriptions'],
            'statuses',
            $errors
        );

        if ($statuses === []) {
            $statuses = $pipeline['_embedded']['statuses'] ?? [];
        }

        $statuses = collect($statuses)->sortBy(fn (array $status) => $this->statusSortValue($status))->values()->all();
        $pipeline['_embedded']['statuses'] = $statuses;

        $leadFields = $this->optionalPaginated($account, '/api/v4/leads/custom_fields', 'custom_fields', [], 'lead_custom_fields', $errors);
        $sources = $this->optionalPaginated($account, '/api/v4/sources', 'sources', [], 'sources', $errors);
        $widgets = $this->installedWidgets($this->optionalPaginated($account, '/api/v4/widgets', 'widgets', [], 'widgets', $errors));
        $websiteButtons = $this->optionalPaginated($account, '/api/v4/website_buttons', 'website_buttons', [], 'website_buttons', $errors);
        $lossReasons = $this->optionalPaginated($account, '/api/v4/leads/loss_reasons', 'loss_reasons', [], 'loss_reasons', $errors);

        return [
            'pipeline' => $pipeline,
            'statuses' => $statuses,
            'stage_rows' => $this->stageRows($statuses, $leadFields, $sources, $pipelineId),
            'lead_custom_fields' => $leadFields,
            'sources' => $this->pipelineRelatedItems($sources, $pipelineId),
            'all_sources' => $sources,
            'widgets' => $widgets,
            'website_buttons' => $this->pipelineRelatedItems($websiteButtons, $pipelineId),
            'all_website_buttons' => $websiteButtons,
            'loss_reasons' => $lossReasons,
            'errors' => $errors,
            'limitations' => [
                'Сохраненные пользовательские фильтры amoCRM и их порядок не отдаются публичным REST API. Их можно воспроизводить в нашем сервисе как отдельные сохраненные представления, когда появится своя аналитика.',
                'Полная конфигурация штатных триггеров, роботов и Salesbot из Digital Pipeline не отдается публичным REST API. На этой странице показываются доступные через API источники, виджеты, кнопки/CRM Plugin и сырые ответы amoCRM.',
            ],
        ];
    }

    public function createPipeline(AmoAccount $account, array $data): array
    {
        $payload = [[
            'name' => $data['name'],
            'sort' => (int) ($data['sort'] ?? 10),
            'is_main' => (bool) ($data['is_main'] ?? false),
            'is_unsorted_on' => (bool) ($data['is_unsorted_on'] ?? true),
            'request_id' => $data['request_id'] ?? str('pipeline-')->append(md5($data['name'].microtime(true)))->toString(),
            '_embedded' => [
                'statuses' => $this->normalizeStatuses($data['statuses'] ?? []),
            ],
        ]];

        return $this->http->post($account, '/api/v4/leads/pipelines', $payload);
    }

    public function createStatuses(AmoAccount $account, int $pipelineId, array $statuses): array
    {
        return $this->http->post(
            $account,
            "/api/v4/leads/pipelines/{$pipelineId}/statuses",
            $this->normalizeStatuses($statuses)
        );
    }

    public function clonePipeline(AmoAccount $account, int $pipelineId, string $name): array
    {
        $details = $this->fetchPipelineDetails($account, $pipelineId);
        $pipeline = $details['pipeline'];
        $statuses = $this->cloneableStatuses($details['statuses']);

        $result = $this->createPipeline($account, [
            'name' => $name,
            'sort' => ((int) ($pipeline['sort'] ?? 10)) + 10,
            'is_main' => false,
            'is_unsorted_on' => (bool) ($pipeline['is_unsorted_on'] ?? true),
            'statuses' => $this->cloneStatuses($statuses),
        ]);

        $warnings = $this->cloneRequiredStatuses($account, $details['lead_custom_fields'] ?? [], $statuses, $result, $pipelineId);

        if ($warnings !== []) {
            $result['_clone_warnings'] = $warnings;
        }

        return $result;
    }

    public function defaultStatuses(): array
    {
        return [
            ['name' => 'Первичный контакт', 'sort' => 10, 'color' => self::DEFAULT_STATUS_COLOR],
            ['name' => 'Квалификация', 'sort' => 20, 'color' => '#fffd7f'],
            ['name' => 'Презентация', 'sort' => 30, 'color' => '#ffce5a'],
            ['name' => 'Согласование', 'sort' => 40, 'color' => '#deff81'],
            ['id' => 142, 'name' => 'Успешно реализовано'],
            ['id' => 143, 'name' => 'Закрыто и не реализовано'],
        ];
    }

    private function normalizeStatuses(array $statuses): array
    {
        return collect($statuses)
            ->filter(fn (array $status) => filled($status['name'] ?? null))
            ->map(function (array $status, int $index): array {
                $payload = [
                    'name' => $status['name'],
                ];

                if (isset($status['id']) && in_array((int) $status['id'], [142, 143], true)) {
                    $payload['id'] = (int) $status['id'];
                }

                if (! isset($payload['id'])) {
                    $payload['sort'] = (int) ($status['sort'] ?? (($index + 1) * 10));
                    $payload['color'] = $this->normalizeStatusColor($status['color'] ?? null);
                    $descriptions = $this->cloneDescriptions($status['descriptions'] ?? []);

                    if ($descriptions !== []) {
                        $payload['descriptions'] = $descriptions;
                    }
                }

                return $payload;
            })
            ->values()
            ->all();
    }

    private function cloneStatuses(iterable $statuses): array
    {
        return $this->cloneableStatuses($statuses)
            ->map(function (array $status): array {
                $payload = ['name' => $status['name']];
                $statusId = (int) ($status['id'] ?? 0);

                if (in_array($statusId, [142, 143], true)) {
                    $payload['id'] = $statusId;

                    return $payload;
                }

                $payload['sort'] = (int) ($status['sort'] ?? 10);
                $payload['color'] = $this->normalizeStatusColor($status['color'] ?? null);
                $descriptions = $this->cloneDescriptions($status['descriptions'] ?? $status['_embedded']['descriptions'] ?? []);

                if ($descriptions !== []) {
                    $payload['descriptions'] = $descriptions;
                }

                return $payload;
            })
            ->values()
            ->all();
    }

    private function cloneableStatuses(iterable $statuses): \Illuminate\Support\Collection
    {
        return collect($statuses)
            ->filter(fn (array $status): bool => filled($status['name'] ?? null) && $this->isCloneableStatus($status))
            ->values();
    }

    private function cloneDescriptions(array $descriptions): array
    {
        return collect($descriptions)
            ->filter(fn (array $description): bool => in_array($description['level'] ?? null, ['newbie', 'candidate', 'master'], true)
                && filled($description['description'] ?? null))
            ->map(fn (array $description): array => [
                'level' => $description['level'],
                'description' => $description['description'],
            ])
            ->unique('level')
            ->values()
            ->all();
    }

    private function normalizeStatusColor(?string $color): string
    {
        $normalized = mb_strtolower((string) $color);

        if (in_array($normalized, self::ALLOWED_STATUS_COLORS, true)) {
            return $normalized;
        }

        return self::DEFAULT_STATUS_COLOR;
    }

    private function isCloneableStatus(array $status): bool
    {
        $statusId = (int) ($status['id'] ?? 0);

        if (in_array($statusId, [142, 143], true)) {
            return true;
        }

        return (int) ($status['type'] ?? 0) === 0;
    }

    private function statusSortValue(array $status): int
    {
        if (isset($status['sort'])) {
            return (int) $status['sort'];
        }

        return match ((int) ($status['id'] ?? 0)) {
            142 => 10000,
            143 => 10010,
            default => 0,
        };
    }

    private function cloneRequiredStatuses(
        AmoAccount $account,
        array $fields,
        \Illuminate\Support\Collection $oldStatuses,
        array $createResult,
        int $oldPipelineId
    ): array {
        $newPipeline = $createResult['_embedded']['pipelines'][0] ?? null;

        if (! is_array($newPipeline) || ! isset($newPipeline['id'])) {
            return ['amoCRM не вернула ID новой воронки, обязательные поля не перенесены.'];
        }

        $statusMap = $this->statusIdMap($oldStatuses, $newPipeline['_embedded']['statuses'] ?? []);

        if ($statusMap === []) {
            return ['amoCRM не вернула этапы новой воронки, обязательные поля не перенесены.'];
        }

        $warnings = [];

        foreach ($fields as $field) {
            $requiredStatuses = $field['required_statuses'] ?? [];

            if (! is_array($requiredStatuses) || $requiredStatuses === []) {
                continue;
            }

            $newRequiredStatuses = $this->mappedRequiredStatuses(
                $requiredStatuses,
                (int) ($newPipeline['id']),
                $statusMap,
                $oldPipelineId
            );

            if ($newRequiredStatuses === []) {
                continue;
            }

            $mergedRequiredStatuses = collect([...$requiredStatuses, ...$newRequiredStatuses])
                ->unique(fn (array $status): string => ($status['pipeline_id'] ?? '').':'.($status['status_id'] ?? ''))
                ->values()
                ->all();

            try {
                $this->http->patch($account, "/api/v4/leads/custom_fields/{$field['id']}", [
                    'name' => $field['name'],
                    'required_statuses' => $mergedRequiredStatuses,
                ]);
            } catch (Throwable $exception) {
                $warnings[] = 'Не удалось перенести обязательность поля "'.($field['name'] ?? $field['id']).'": '.$exception->getMessage();
            }
        }

        return $warnings;
    }

    private function statusIdMap(\Illuminate\Support\Collection $oldStatuses, array $newStatuses): array
    {
        $newRegularStatuses = collect($newStatuses)
            ->filter(fn (array $status): bool => ! in_array((int) ($status['id'] ?? 0), [142, 143], true)
                && (int) ($status['type'] ?? 0) === 0)
            ->values();
        $map = [
            142 => 142,
            143 => 143,
        ];
        $regularIndex = 0;

        foreach ($oldStatuses as $oldStatus) {
            $oldStatusId = (int) ($oldStatus['id'] ?? 0);

            if (in_array($oldStatusId, [142, 143], true)) {
                continue;
            }

            $newStatus = $newRegularStatuses[$regularIndex] ?? null;
            if (is_array($newStatus) && isset($newStatus['id'])) {
                $map[$oldStatusId] = (int) $newStatus['id'];
            }
            $regularIndex++;
        }

        return $map;
    }

    private function mappedRequiredStatuses(array $requiredStatuses, int $newPipelineId, array $statusMap, int $oldPipelineId): array
    {
        return collect($requiredStatuses)
            ->filter(fn (array $status): bool => (int) ($status['pipeline_id'] ?? 0) === $oldPipelineId
                && isset($statusMap[(int) ($status['status_id'] ?? 0)]))
            ->map(fn (array $status): array => [
                'pipeline_id' => $newPipelineId,
                'status_id' => $statusMap[(int) $status['status_id']],
            ])
            ->values()
            ->all();
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

    private function optionalGet(AmoAccount $account, string $path, array $query, string $label, array &$errors): array
    {
        try {
            return $this->http->get($account, $path, $query);
        } catch (Throwable $exception) {
            $errors[$label] = $exception->getMessage();

            return [];
        }
    }

    private function optionalPaginated(
        AmoAccount $account,
        string $path,
        string $embeddedKey,
        array $query,
        string $label,
        array &$errors
    ): array {
        try {
            return $this->fetchPaginated($account, $path, $embeddedKey, $query);
        } catch (Throwable $exception) {
            $errors[$label] = $exception->getMessage();

            return [];
        }
    }

    private function stageRows(array $statuses, array $leadFields, array $sources, int $pipelineId): array
    {
        return collect($statuses)
            ->map(fn (array $status): array => [
                'status' => $status,
                'description' => $this->statusDescription($status),
                'required_fields' => $this->requiredFieldsForStatus($leadFields, $pipelineId, (int) ($status['id'] ?? 0)),
                'sources' => $this->sourcesForStatus($sources, $pipelineId, (int) ($status['id'] ?? 0)),
            ])
            ->values()
            ->all();
    }

    private function statusDescription(array $status): ?string
    {
        $descriptions = $status['_embedded']['descriptions'] ?? $status['descriptions'] ?? [];

        if (is_array($descriptions)) {
            $first = collect($descriptions)->first();

            if (is_array($first)) {
                return $first['description'] ?? $first['name'] ?? $first['text'] ?? null;
            }

            if (is_string($first)) {
                return $first;
            }
        }

        return $status['description'] ?? null;
    }

    private function requiredFieldsForStatus(array $fields, int $pipelineId, int $statusId): array
    {
        return collect($fields)
            ->filter(function (array $field) use ($pipelineId, $statusId): bool {
                foreach (($field['required_statuses'] ?? []) as $requiredStatus) {
                    if ((int) ($requiredStatus['pipeline_id'] ?? 0) === $pipelineId
                        && (int) ($requiredStatus['status_id'] ?? 0) === $statusId) {
                        return true;
                    }
                }

                return false;
            })
            ->sortBy(fn (array $field) => (int) ($field['sort'] ?? 0))
            ->values()
            ->all();
    }

    private function sourcesForStatus(array $sources, int $pipelineId, int $statusId): array
    {
        return collect($sources)
            ->filter(function (array $source) use ($pipelineId, $statusId): bool {
                $sourcePipelineId = $source['pipeline_id'] ?? $source['default_pipeline_id'] ?? null;
                $sourceStatusId = $source['status_id'] ?? $source['default_status_id'] ?? null;

                return (int) $sourcePipelineId === $pipelineId && (int) $sourceStatusId === $statusId;
            })
            ->values()
            ->all();
    }

    private function pipelineRelatedItems(array $items, int $pipelineId): array
    {
        return collect($items)
            ->filter(function (array $item) use ($pipelineId): bool {
                if ((int) ($item['pipeline_id'] ?? $item['default_pipeline_id'] ?? 0) === $pipelineId) {
                    return true;
                }

                $encoded = json_encode($item, JSON_UNESCAPED_UNICODE);

                return is_string($encoded) && str_contains($encoded, (string) $pipelineId);
            })
            ->values()
            ->all();
    }

    private function installedWidgets(array $widgets): array
    {
        return collect($widgets)
            ->filter(function (array $widget): bool {
                if (array_key_exists('is_installed', $widget)) {
                    return (bool) $widget['is_installed'];
                }

                if (array_key_exists('installed', $widget)) {
                    return (bool) $widget['installed'];
                }

                if (isset($widget['status'])) {
                    return in_array($widget['status'], ['installed', 'active', 'enabled'], true);
                }

                if (array_key_exists('is_active', $widget) || array_key_exists('is_enabled', $widget)) {
                    return (bool) ($widget['is_active'] ?? $widget['is_enabled'] ?? false);
                }

                return false;
            })
            ->values()
            ->all();
    }
}
