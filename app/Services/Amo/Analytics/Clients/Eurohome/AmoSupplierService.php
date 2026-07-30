<?php

declare(strict_types=1);

namespace App\Services\Amo\Analytics\Clients\Eurohome;

use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AmoSupplierService
{
    private const DEFAULT_SUPPLIER_FIELD_ID = 871209;
    private const UNGROUPED_LABEL = 'Без поставщика';

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
        string $supplierFilter = '',
        int $limit = 300,
        string $timezone = 'UTC'
    ): array {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $supplierFieldId = (int) data_get($config, 'supplier_field_id', self::DEFAULT_SUPPLIER_FIELD_ID);
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
            ->chunkById(500, function ($chunk) use (&$leads, &$total, $limit, $fromDate, $toDate, $timezone, $supplierFieldId, $supplierFilter): void {
                foreach ($chunk as $lead) {
                    $createdAt = $lead->entity_created_at?->copy()->setTimezone($timezone);
                    if (!$this->dateInRange($createdAt, $fromDate, $toDate)) {
                        continue;
                    }

                    $customFields = $lead->custom_fields_values ?? [];
                    $suppliers = $this->multiselectFieldLabels($customFields, $supplierFieldId);
                    if ($suppliers === []) {
                        $suppliers = [self::UNGROUPED_LABEL];
                    }

                    if ($supplierFilter !== '' && !in_array($supplierFilter, $suppliers, true)) {
                        continue;
                    }

                    $total++;
                    if (count($leads) < $limit) {
                        $leads[] = [
                            'id' => $lead->external_id,
                            'name' => $lead->name ?: 'Без названия',
                            'suppliers' => $suppliers,
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
        $supplierFieldId = (int) data_get($config, 'supplier_field_id', self::DEFAULT_SUPPLIER_FIELD_ID);
        $excludedStatusIds = $this->excludedStatusIds($account, $pipelineId);

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $suppliers = [];
        $dealCount = 0;
        $budgetTotal = 0.0;

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'entity_created_at', 'custom_fields_values', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($excludedStatusIds !== [], fn ($q) => $q->whereNotIn('status_id', $excludedStatusIds))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$suppliers, &$dealCount, &$budgetTotal, $fromDate, $toDate, $timezone, $supplierFieldId): void {
                foreach ($chunk as $lead) {
                    $createdAt = $lead->entity_created_at?->copy()->setTimezone($timezone);
                    if (!$this->dateInRange($createdAt, $fromDate, $toDate)) {
                        continue;
                    }

                    $customFields = $lead->custom_fields_values ?? [];
                    $price = (float) ($lead->raw['price'] ?? 0);

                    $dealCount++;
                    $budgetTotal += $price;

                    $leadSuppliers = $this->multiselectFieldLabels($customFields, $supplierFieldId);
                    if ($leadSuppliers === []) {
                        $leadSuppliers = [self::UNGROUPED_LABEL];
                    }

                    foreach ($leadSuppliers as $supplierName) {
                        $suppliers[$supplierName] ??= ['budgetTotal' => 0.0, 'dealCount' => 0];
                        $suppliers[$supplierName]['budgetTotal'] += $price;
                        $suppliers[$supplierName]['dealCount']++;
                    }
                }
            });

        arsort($suppliers);

        $supplierList = array_map(
            fn (string $name, array $data): array => [
                'name' => $name,
                'budgetTotal' => round($data['budgetTotal']),
                'dealCount' => $data['dealCount'],
            ],
            array_keys($suppliers),
            array_values($suppliers),
        );

        return [
            'summary' => [
                'supplierCount' => count($supplierList),
                'dealCount' => $dealCount,
                'budgetTotal' => round($budgetTotal),
            ],
            'suppliers' => $supplierList,
        ];
    }

    // Same exclusion rule as AmoProductGroupService::excludedStatusIds() — won/lost/deferred stages
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
            'amo_supplier_breakdown',
            $account->id,
            $from?->toDateString() ?? 'null',
            $to?->toDateString() ?? 'null',
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'supplier_field_id') ?: self::DEFAULT_SUPPLIER_FIELD_ID,
            $timezone,
        ]);
    }
}
