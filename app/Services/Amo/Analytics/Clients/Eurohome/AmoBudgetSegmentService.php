<?php

declare(strict_types=1);

namespace App\Services\Amo\Analytics\Clients\Eurohome;

use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AmoBudgetSegmentService
{
    // Derived from the live active-deal price distribution (see migration note) — kept as the
    // fallback when a widget installation has no explicit `budget_segments` in its config.
    private const DEFAULT_SEGMENTS = [
        ['label' => 'до 300 000', 'min' => 0, 'max' => 300000],
        ['label' => '300 000 – 700 000', 'min' => 300000, 'max' => 700000],
        ['label' => '700 000 – 1 500 000', 'min' => 700000, 'max' => 1500000],
        ['label' => '1 500 000 – 3 000 000', 'min' => 1500000, 'max' => 3000000],
        ['label' => 'свыше 3 000 000', 'min' => 3000000, 'max' => null],
    ];

    public function breakdown(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone = 'UTC'): array
    {
        return Cache::remember(
            $this->cacheKey($account, $from, $to, $config, $timezone),
            now()->addMinutes(10),
            fn (): array => $this->buildBreakdown($account, $from, $to, $config, $timezone),
        );
    }

    public function leads(
        AmoAccount $account,
        ?Carbon $from,
        ?Carbon $to,
        array $config,
        string $segmentFilter = '',
        int $limit = 300,
        string $timezone = 'UTC'
    ): array {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $segments = $this->segments($config);
        $excludedStatusIds = $this->excludedStatusIds($account, $pipelineId);

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $leads = [];
        $total = 0;

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'entity_created_at', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($excludedStatusIds !== [], fn ($q) => $q->whereNotIn('status_id', $excludedStatusIds))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$leads, &$total, $limit, $fromDate, $toDate, $timezone, $segments, $segmentFilter): void {
                foreach ($chunk as $lead) {
                    $createdAt = $lead->entity_created_at?->copy()->setTimezone($timezone);
                    if (!$this->dateInRange($createdAt, $fromDate, $toDate)) {
                        continue;
                    }

                    $price = (float) ($lead->raw['price'] ?? 0);
                    if ($price <= 0) {
                        continue;
                    }

                    $segmentLabel = $this->segmentLabel($price, $segments);

                    if ($segmentFilter !== '' && $segmentLabel !== $segmentFilter) {
                        continue;
                    }

                    $total++;
                    if (count($leads) < $limit) {
                        $leads[] = [
                            'id' => $lead->external_id,
                            'name' => $lead->name ?: 'Без названия',
                            'segment' => $segmentLabel,
                            'created_date' => $createdAt?->toDateString(),
                            'price' => $price,
                        ];
                    }
                }
            });

        usort($leads, fn (array $a, array $b) => $b['price'] <=> $a['price']);

        return [
            'leads' => $leads,
            'total' => $total,
            'limited' => $total > $limit,
            'limit' => $limit,
        ];
    }

    private function buildBreakdown(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone): array
    {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $segments = $this->segments($config);
        $excludedStatusIds = $this->excludedStatusIds($account, $pipelineId);

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $counts = array_fill_keys(array_column($segments, 'label'), ['dealCount' => 0, 'budgetTotal' => 0.0]);

        CrmEntitySnapshot::query()
            ->select(['id', 'entity_created_at', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($excludedStatusIds !== [], fn ($q) => $q->whereNotIn('status_id', $excludedStatusIds))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$counts, $fromDate, $toDate, $timezone, $segments): void {
                foreach ($chunk as $lead) {
                    $createdAt = $lead->entity_created_at?->copy()->setTimezone($timezone);
                    if (!$this->dateInRange($createdAt, $fromDate, $toDate)) {
                        continue;
                    }

                    $price = (float) ($lead->raw['price'] ?? 0);
                    if ($price <= 0) {
                        continue;
                    }

                    $label = $this->segmentLabel($price, $segments);
                    $counts[$label]['dealCount']++;
                    $counts[$label]['budgetTotal'] += $price;
                }
            });

        $segmentList = array_map(
            fn (string $label, array $data): array => [
                'name' => $label,
                'dealCount' => $data['dealCount'],
                'budgetTotal' => round($data['budgetTotal']),
            ],
            array_keys($counts),
            array_values($counts),
        );

        $dealCount = array_sum(array_column($segmentList, 'dealCount'));
        $budgetTotal = array_sum(array_column($segmentList, 'budgetTotal'));

        return [
            'summary' => [
                'segmentCount' => count($segmentList),
                'dealCount' => $dealCount,
                'budgetTotal' => round($budgetTotal),
            ],
            'segments' => $segmentList,
        ];
    }

    // Segments partition price ranges exclusively (unlike the multiselect-driven product-group/supplier
    // reports), so — unlike those — a totals row here is meaningful.
    private function segments(array $config): array
    {
        $configured = data_get($config, 'budget_segments');
        if (!is_array($configured) || $configured === []) {
            return self::DEFAULT_SEGMENTS;
        }

        return array_map(fn (array $s): array => [
            'label' => (string) ($s['label'] ?? ''),
            'min' => (float) ($s['min'] ?? 0),
            'max' => isset($s['max']) && $s['max'] !== null ? (float) $s['max'] : null,
        ], $configured);
    }

    private function segmentLabel(float $price, array $segments): string
    {
        foreach ($segments as $segment) {
            if ($price >= $segment['min'] && ($segment['max'] === null || $price < $segment['max'])) {
                return $segment['label'];
            }
        }

        // Falls outside every configured range (e.g. a negative price) — bucket with the first segment.
        return $segments[0]['label'] ?? '';
    }

    // Same exclusion rule as the sibling Eurohome widget services — won/lost/deferred stages
    // don't count as "active" deals. Kept as a local copy since each widget service is self-contained.
    private function excludedStatusIds(AmoAccount $account, int $pipelineId): array
    {
        return CrmPipelineStatusSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->when($pipelineId > 0, fn ($q) => $q->where('amo_pipeline_id', $pipelineId))
            ->where(function ($q): void {
                $q->whereIn('amo_status_id', [142, 143])
                    ->orWhere('name', 'like', '%тлож%')
                    ->orWhere('name', 'like', '%аморожен%');
            })
            ->pluck('amo_status_id')
            ->toArray();
    }

    private function dateInRange(?Carbon $date, ?string $fromDate, ?string $toDate): bool
    {
        if ($date === null) {
            return false;
        }

        $d = $date->toDateString();

        if ($fromDate !== null && $d < $fromDate) {
            return false;
        }

        if ($toDate !== null && $d > $toDate) {
            return false;
        }

        return true;
    }

    private function cacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone): string
    {
        return implode(':', [
            'amo_budget_segment_breakdown',
            $account->id,
            $from?->toDateString() ?? 'null',
            $to?->toDateString() ?? 'null',
            data_get($config, 'pipeline_id') ?: 'all',
            md5(json_encode($this->segments($config))),
            $timezone,
        ]);
    }
}
