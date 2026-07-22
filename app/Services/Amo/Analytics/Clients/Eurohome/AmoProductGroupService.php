<?php

declare(strict_types=1);

namespace App\Services\Amo\Analytics\Clients\Eurohome;

use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AmoProductGroupService
{
    private const DEFAULT_PRODUCT_GROUP_FIELD_ID = 871211;
    private const UNGROUPED_LABEL = 'Без группы';

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
        string $groupFilter = '',
        int $limit = 300,
        string $timezone = 'UTC'
    ): array {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $productGroupFieldId = (int) data_get($config, 'product_group_field_id', self::DEFAULT_PRODUCT_GROUP_FIELD_ID);
        $excludedStatusIds = $this->excludedStatusIds($account, $pipelineId);

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $leads = [];
        $total = 0;

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'entity_created_at', 'custom_fields_values', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($excludedStatusIds !== [], fn ($q) => $q->whereNotIn('status_id', $excludedStatusIds))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$leads, &$total, $limit, $fromDate, $toDate, $timezone, $productGroupFieldId, $groupFilter): void {
                foreach ($chunk as $lead) {
                    $createdAt = $lead->entity_created_at?->copy()->setTimezone($timezone);
                    if (!$this->dateInRange($createdAt, $fromDate, $toDate)) {
                        continue;
                    }

                    $customFields = $lead->custom_fields_values ?? [];
                    $groups = $this->multiselectFieldLabels($customFields, $productGroupFieldId);
                    if ($groups === []) {
                        $groups = [self::UNGROUPED_LABEL];
                    }

                    if ($groupFilter !== '' && !in_array($groupFilter, $groups, true)) {
                        continue;
                    }

                    $total++;
                    if (count($leads) < $limit) {
                        $leads[] = [
                            'id' => $lead->external_id,
                            'name' => $lead->name ?: 'Без названия',
                            'groups' => $groups,
                            'created_date' => $createdAt?->toDateString(),
                            'price' => (float) ($lead->raw['price'] ?? 0),
                        ];
                    }
                }
            });

        usort($leads, fn ($a, $b) => $b['price'] <=> $a['price']);

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
        $productGroupFieldId = (int) data_get($config, 'product_group_field_id', self::DEFAULT_PRODUCT_GROUP_FIELD_ID);
        $excludedStatusIds = $this->excludedStatusIds($account, $pipelineId);

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $groups = [];
        $dealCount = 0;
        $budgetTotal = 0.0;

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'entity_created_at', 'custom_fields_values', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($excludedStatusIds !== [], fn ($q) => $q->whereNotIn('status_id', $excludedStatusIds))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$groups, &$dealCount, &$budgetTotal, $fromDate, $toDate, $timezone, $productGroupFieldId): void {
                foreach ($chunk as $lead) {
                    $createdAt = $lead->entity_created_at?->copy()->setTimezone($timezone);
                    if (!$this->dateInRange($createdAt, $fromDate, $toDate)) {
                        continue;
                    }

                    $customFields = $lead->custom_fields_values ?? [];
                    $price = (float) ($lead->raw['price'] ?? 0);

                    $dealCount++;
                    $budgetTotal += $price;

                    $leadGroups = $this->multiselectFieldLabels($customFields, $productGroupFieldId);
                    if ($leadGroups === []) {
                        $leadGroups = [self::UNGROUPED_LABEL];
                    }

                    foreach ($leadGroups as $groupName) {
                        $groups[$groupName] ??= ['budgetTotal' => 0.0, 'dealCount' => 0];
                        $groups[$groupName]['budgetTotal'] += $price;
                        $groups[$groupName]['dealCount']++;
                    }
                }
            });

        arsort($groups);

        $groupList = array_map(
            fn (string $name, array $data): array => [
                'name' => $name,
                'budgetTotal' => round($data['budgetTotal']),
                'dealCount' => $data['dealCount'],
            ],
            array_keys($groups),
            array_values($groups),
        );

        return [
            'summary' => [
                'groupCount' => count($groupList),
                'dealCount' => $dealCount,
                'budgetTotal' => round($budgetTotal),
            ],
            'groups' => $groupList,
        ];
    }

    // Same exclusion rule as AmoManagerTopupService::excludedStatusIds() — won/lost/deferred stages
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

    // Multiselect fields store one entry per selected option in `values`, unlike single-select fields.
    private function multiselectFieldLabels(array $customFields, int $fieldId): array
    {
        foreach ($customFields as $field) {
            $fId = (int) ($field['field_id'] ?? $field['id'] ?? 0);
            if ($fId === $fieldId) {
                return array_values(array_filter(array_map(
                    fn (array $v): string => (string) ($v['value'] ?? ''),
                    $field['values'] ?? [],
                )));
            }
        }

        return [];
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
            'amo_product_group_breakdown',
            $account->id,
            $from?->toDateString() ?? 'null',
            $to?->toDateString() ?? 'null',
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'product_group_field_id') ?: self::DEFAULT_PRODUCT_GROUP_FIELD_ID,
            $timezone,
        ]);
    }
}
