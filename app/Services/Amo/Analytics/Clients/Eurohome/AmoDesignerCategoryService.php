<?php

declare(strict_types=1);

namespace App\Services\Amo\Analytics\Clients\Eurohome;

use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AmoDesignerCategoryService
{
    private const DEFAULT_CATEGORY_FIELD_ID = 845859;
    private const UNCATEGORIZED_LABEL = 'Без категории';

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
        string $categoryFilter = '',
        int $limit = 300,
        string $timezone = 'UTC'
    ): array {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $categoryFieldId = (int) data_get($config, 'category_field_id', self::DEFAULT_CATEGORY_FIELD_ID);

        $rows = $this->collectLeadRows($account, $from, $to, $pipelineId, $timezone);
        $categories = $this->resolveCategories($account, array_column($rows, 'contact_id'), $categoryFieldId);

        $leads = [];
        $total = 0;

        foreach ($rows as $row) {
            $category = $row['contact_id'] !== null
                ? ($categories[$row['contact_id']] ?? self::UNCATEGORIZED_LABEL)
                : self::UNCATEGORIZED_LABEL;

            if ($categoryFilter !== '' && $category !== $categoryFilter) {
                continue;
            }

            $total++;
            if (count($leads) < $limit) {
                $leads[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'category' => $category,
                    'created_date' => $row['created_date'],
                    'price' => $row['price'],
                ];
            }
        }

        usort($leads, fn (array $a, array $b) => strcmp($b['created_date'] ?? '', $a['created_date'] ?? ''));

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
        $categoryFieldId = (int) data_get($config, 'category_field_id', self::DEFAULT_CATEGORY_FIELD_ID);

        $rows = $this->collectLeadRows($account, $from, $to, $pipelineId, $timezone);
        $categories = $this->resolveCategories($account, array_column($rows, 'contact_id'), $categoryFieldId);

        $counts = [];
        foreach ($rows as $row) {
            $label = $row['contact_id'] !== null
                ? ($categories[$row['contact_id']] ?? self::UNCATEGORIZED_LABEL)
                : self::UNCATEGORIZED_LABEL;
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        arsort($counts);

        // Keep the uncategorized bucket last regardless of its count — it's a fallback, not a real category.
        if (isset($counts[self::UNCATEGORIZED_LABEL])) {
            $uncategorized = $counts[self::UNCATEGORIZED_LABEL];
            unset($counts[self::UNCATEGORIZED_LABEL]);
            $counts[self::UNCATEGORIZED_LABEL] = $uncategorized;
        }

        $categoryList = array_map(
            fn (string $name, int $count): array => ['name' => $name, 'dealCount' => $count],
            array_keys($counts),
            array_values($counts),
        );

        return [
            'summary' => [
                'categoryCount' => count($categoryList),
                'dealCount' => count($rows),
            ],
            'categories' => $categoryList,
        ];
    }

    // Collects active leads in range with their main-contact id, shared by breakdown() and leads()
    // so both read the exact same deal set.
    private function collectLeadRows(AmoAccount $account, ?Carbon $from, ?Carbon $to, int $pipelineId, string $timezone): array
    {
        $excludedStatusIds = $this->excludedStatusIds($account, $pipelineId);
        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $rows = [];

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'entity_created_at', 'embedded', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($excludedStatusIds !== [], fn ($q) => $q->whereNotIn('status_id', $excludedStatusIds))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$rows, $fromDate, $toDate, $timezone): void {
                foreach ($chunk as $lead) {
                    $createdAt = $lead->entity_created_at?->copy()->setTimezone($timezone);
                    if (!$this->dateInRange($createdAt, $fromDate, $toDate)) {
                        continue;
                    }

                    $rows[] = [
                        'id' => $lead->external_id,
                        'name' => $lead->name ?: 'Без названия',
                        'created_date' => $createdAt?->toDateString(),
                        'price' => (float) ($lead->raw['price'] ?? 0),
                        'contact_id' => $this->mainContactId($lead->embedded ?? []),
                    ];
                }
            });

        return $rows;
    }

    // Category lives on the deal's linked contact — same "main contact" resolution as
    // AmoManagerTopupService::designerTarget(), but companies never carry a category (fall through to uncategorized).
    private function mainContactId(?array $embedded): ?int
    {
        $contacts = $embedded['contacts'] ?? [];
        if ($contacts === []) {
            return null;
        }

        foreach ($contacts as $contact) {
            if (($contact['is_main'] ?? false) === true) {
                return (int) $contact['id'];
            }
        }

        return (int) $contacts[0]['id'];
    }

    private function resolveCategories(AmoAccount $account, array $contactIds, int $categoryFieldId): array
    {
        $contactIds = array_values(array_unique(array_filter($contactIds, fn (?int $id): bool => $id !== null)));
        if ($contactIds === []) {
            return [];
        }

        $categories = [];

        CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'contacts')
            ->whereIn('external_id', array_map('strval', $contactIds))
            ->get(['external_id', 'custom_fields_values'])
            ->each(function (CrmEntitySnapshot $contact) use (&$categories, $categoryFieldId): void {
                $label = $this->textFieldValue($contact->custom_fields_values ?? [], $categoryFieldId);
                $categories[(int) $contact->external_id] = $label !== null && $label !== '' ? $label : self::UNCATEGORIZED_LABEL;
            });

        return $categories;
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

    private function textFieldValue(array $customFields, int $fieldId): ?string
    {
        foreach ($customFields as $field) {
            $fId = (int) ($field['field_id'] ?? $field['id'] ?? 0);
            if ($fId === $fieldId) {
                $value = (string) ($field['values'][0]['value'] ?? '');
                return $value !== '' ? $value : null;
            }
        }

        return null;
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
            'amo_designer_category_breakdown',
            $account->id,
            $from?->toDateString() ?? 'null',
            $to?->toDateString() ?? 'null',
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'category_field_id') ?: self::DEFAULT_CATEGORY_FIELD_ID,
            $timezone,
        ]);
    }
}
