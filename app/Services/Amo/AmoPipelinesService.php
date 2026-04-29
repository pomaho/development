<?php

namespace App\Services\Amo;

use App\Models\AmoAccount;

class AmoPipelinesService
{
    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function fetchPipelines(AmoAccount $account): array
    {
        $payload = $this->http->get($account, '/api/v4/leads/pipelines');

        return $payload['_embedded']['pipelines'] ?? [];
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

    public function defaultStatuses(): array
    {
        return [
            ['name' => 'Первичный контакт', 'sort' => 10, 'color' => '#99ccff'],
            ['name' => 'Квалификация', 'sort' => 20, 'color' => '#fffd7f'],
            ['name' => 'Презентация', 'sort' => 30, 'color' => '#ffcc66'],
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
                    $payload['color'] = $status['color'] ?? '#99ccff';
                }

                return $payload;
            })
            ->values()
            ->all();
    }
}
