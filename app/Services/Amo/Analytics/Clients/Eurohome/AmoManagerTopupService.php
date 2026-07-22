<?php

declare(strict_types=1);

namespace App\Services\Amo\Analytics\Clients\Eurohome;

use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AmoManagerTopupService
{
    private const DEFAULT_PREPAYMENT_FIELD_ID = 845975;
    private const DEFAULT_MANAGER_FIELD_ID = 845835;
    private const DEFAULT_TOPUP_DATE_FIELD_ID = 845843;
    private const DEFAULT_CATEGORY_FIELD_ID = 845859;
    // amoCRM reserves status_id 142 ("Успешно реализовано") as a fixed system id on every pipeline —
    // the status `type` field is NOT populated with 142/143 in practice, so it can't be used to detect this stage.
    private const WON_STATUS_ID = 142;

    public function breakdown(
        AmoAccount $account,
        ?Carbon $from,
        ?Carbon $to,
        array $config,
        array $selectedManagers = [],
        string $timezone = 'UTC'
    ): array {
        return Cache::remember(
            $this->cacheKey($account, $from, $to, $config, $selectedManagers, $timezone),
            now()->addMinutes(10),
            fn (): array => $this->buildBreakdown($account, $from, $to, $config, $selectedManagers, $timezone),
        );
    }

    public function leads(
        AmoAccount $account,
        ?Carbon $from,
        ?Carbon $to,
        array $config,
        string $managerFilter = '',
        int $limit = 300,
        string $timezone = 'UTC'
    ): array {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $prepaymentFieldId = (int) data_get($config, 'prepayment_field_id', self::DEFAULT_PREPAYMENT_FIELD_ID);
        $managerFieldId = (int) data_get($config, 'manager_field_id', self::DEFAULT_MANAGER_FIELD_ID);
        $topupDateFieldId = (int) data_get($config, 'topup_date_field_id', self::DEFAULT_TOPUP_DATE_FIELD_ID);
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
            ->chunkById(500, function ($chunk) use (&$leads, &$total, $limit, $fromDate, $toDate, $timezone, $prepaymentFieldId, $managerFieldId, $topupDateFieldId, $managerFilter): void {
                foreach ($chunk as $lead) {
                    $customFields = $lead->custom_fields_values ?? [];
                    $raw = $lead->raw ?? [];

                    $topupDate = $this->dateFieldValue($customFields, $topupDateFieldId, $timezone);
                    if (!$this->dateInRange($topupDate, $fromDate, $toDate)) {
                        continue;
                    }

                    $managerName = $this->textFieldValue($customFields, $managerFieldId);
                    if (empty($managerName)) {
                        continue;
                    }

                    if ($managerFilter !== '' && $managerName !== $managerFilter) {
                        continue;
                    }

                    $price = (float) ($raw['price'] ?? 0);
                    if ($price <= 0) {
                        continue;
                    }

                    $prepayment = $this->numericFieldValue($customFields, $prepaymentFieldId);
                    if ($prepayment === null) {
                        continue;
                    }

                    $topup = $price - $prepayment;
                    if ($topup <= 0) {
                        continue;
                    }

                    $total++;
                    if (count($leads) < $limit) {
                        $leads[] = [
                            'id' => $lead->external_id,
                            'name' => $lead->name ?: 'Без названия',
                            'manager' => $managerName,
                            'topup_date' => $topupDate?->toDateString(),
                            'price' => $price,
                            'prepayment' => $prepayment,
                            'topup' => $topup,
                        ];
                    }
                }
            });

        usort($leads, fn ($a, $b) => $b['topup'] <=> $a['topup']);

        return [
            'leads' => $leads,
            'total' => $total,
            'limited' => $total > $limit,
            'limit' => $limit,
        ];
    }

    public function designerBreakdown(
        AmoAccount $account,
        ?Carbon $from,
        ?Carbon $to,
        array $config,
        string $timezone = 'UTC'
    ): array {
        return Cache::remember(
            $this->designerCacheKey($account, $from, $to, $config, $timezone),
            now()->addMinutes(10),
            fn (): array => $this->buildDesignerBreakdown($account, $from, $to, $config, $timezone),
        );
    }

    public function designerLeads(
        AmoAccount $account,
        ?Carbon $from,
        ?Carbon $to,
        array $config,
        string $designerKey,
        int $limit = 300,
        string $timezone = 'UTC'
    ): array {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $wonStatusIds = $this->wonStatusIds($account, $pipelineId);

        if ($wonStatusIds === [] || $designerKey === '') {
            return ['leads' => [], 'total' => 0, 'limited' => false, 'limit' => $limit];
        }

        [$designerType, $designerId] = $this->parseDesignerKey($designerKey);

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $leads = [];
        $total = 0;

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'entity_closed_at', 'embedded', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->whereIn('status_id', $wonStatusIds)
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$leads, &$total, $limit, $fromDate, $toDate, $timezone, $designerType, $designerId): void {
                foreach ($chunk as $lead) {
                    $closedAt = $lead->entity_closed_at?->copy()->setTimezone($timezone);
                    if (!$this->dateInRange($closedAt, $fromDate, $toDate)) {
                        continue;
                    }

                    $price = (float) ($lead->raw['price'] ?? 0);
                    if ($price <= 0) {
                        continue;
                    }

                    $target = $this->designerTarget($lead->embedded ?? []);
                    if ($target === null || $target['type'] !== $designerType || $target['id'] !== $designerId) {
                        continue;
                    }

                    $total++;
                    if (count($leads) < $limit) {
                        $leads[] = [
                            'id' => $lead->external_id,
                            'name' => $lead->name ?: 'Без названия',
                            'closed_date' => $closedAt?->toDateString(),
                            'price' => $price,
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

    private function buildBreakdown(
        AmoAccount $account,
        ?Carbon $from,
        ?Carbon $to,
        array $config,
        array $selectedManagers,
        string $timezone
    ): array {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $prepaymentFieldId = (int) data_get($config, 'prepayment_field_id', self::DEFAULT_PREPAYMENT_FIELD_ID);
        $managerFieldId = (int) data_get($config, 'manager_field_id', self::DEFAULT_MANAGER_FIELD_ID);
        $topupDateFieldId = (int) data_get($config, 'topup_date_field_id', self::DEFAULT_TOPUP_DATE_FIELD_ID);
        $excludedStatusIds = $this->excludedStatusIds($account, $pipelineId);

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $managers = [];
        $allManagerNames = [];
        $monthlyTotals = [];

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'entity_created_at', 'custom_fields_values', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($excludedStatusIds !== [], fn ($q) => $q->whereNotIn('status_id', $excludedStatusIds))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$managers, &$allManagerNames, &$monthlyTotals, $fromDate, $toDate, $timezone, $prepaymentFieldId, $managerFieldId, $topupDateFieldId, $selectedManagers): void {
                foreach ($chunk as $lead) {
                    $customFields = $lead->custom_fields_values ?? [];
                    $raw = $lead->raw ?? [];

                    $topupDate = $this->dateFieldValue($customFields, $topupDateFieldId, $timezone);
                    if (!$this->dateInRange($topupDate, $fromDate, $toDate)) {
                        continue;
                    }

                    $managerName = $this->textFieldValue($customFields, $managerFieldId);
                    if (empty($managerName)) {
                        continue;
                    }

                    $price = (float) ($raw['price'] ?? 0);
                    if ($price <= 0) {
                        continue;
                    }

                    $prepayment = $this->numericFieldValue($customFields, $prepaymentFieldId);
                    if ($prepayment === null) {
                        continue;
                    }

                    $topup = $price - $prepayment;
                    if ($topup <= 0) {
                        continue;
                    }

                    $allManagerNames[$managerName] = true;

                    if ($selectedManagers !== [] && !in_array($managerName, $selectedManagers, true)) {
                        continue;
                    }

                    $managers[$managerName] ??= ['topupTotal' => 0.0, 'dealCount' => 0];
                    $managers[$managerName]['topupTotal'] += $topup;
                    $managers[$managerName]['dealCount']++;

                    $month = $topupDate?->format('Y-m') ?? $lead->entity_created_at?->format('Y-m');
                    if ($month) {
                        $monthlyTotals[$month] = ($monthlyTotals[$month] ?? 0.0) + $topup;
                    }
                }
            });

        arsort($managers);
        ksort($monthlyTotals);

        $managerList = array_map(
            fn (string $name, array $data): array => [
                'name' => $name,
                'topupTotal' => round($data['topupTotal']),
                'dealCount' => $data['dealCount'],
            ],
            array_keys($managers),
            array_values($managers),
        );

        $monthlyList = array_map(
            fn (string $month, float $total): array => [
                'month' => $month,
                'total' => round($total),
            ],
            array_keys($monthlyTotals),
            array_values($monthlyTotals),
        );

        $grandTotal = array_sum(array_column($managerList, 'topupTotal'));
        $dealCount = array_sum(array_column($managerList, 'dealCount'));

        return [
            'summary' => [
                'managerCount' => count($managerList),
                'dealCount' => $dealCount,
                'topupTotal' => round($grandTotal),
            ],
            'allManagerNames' => array_keys($allManagerNames),
            'managers' => $managerList,
            'monthlyBreakdown' => $monthlyList,
        ];
    }

    private function excludedStatusIds(AmoAccount $account, int $pipelineId): array
    {
        return CrmPipelineStatusSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->when($pipelineId > 0, fn ($q) => $q->where('amo_pipeline_id', $pipelineId))
            ->where(function ($q): void {
                // 142/143 are amoCRM's fixed status_id values for "Успешно реализовано" / "Закрыто и не реализовано" —
                // the status `type` field is not populated with 142/143 in practice, so it can't be used here.
                $q->whereIn('amo_status_id', [142, 143])
                    ->orWhere('name', 'like', '%тлож%')
                    ->orWhere('name', 'like', '%аморожен%');
            })
            ->pluck('amo_status_id')
            ->toArray();
    }

    private function buildDesignerBreakdown(
        AmoAccount $account,
        ?Carbon $from,
        ?Carbon $to,
        array $config,
        string $timezone
    ): array {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $categoryFieldId = (int) data_get($config, 'category_field_id', self::DEFAULT_CATEGORY_FIELD_ID);
        $wonStatusIds = $this->wonStatusIds($account, $pipelineId);

        $empty = ['summary' => ['designerCount' => 0, 'dealCount' => 0, 'budgetTotal' => 0.0], 'designers' => []];

        if ($wonStatusIds === []) {
            return $empty;
        }

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $designers = [];

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'entity_closed_at', 'embedded', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->whereIn('status_id', $wonStatusIds)
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$designers, $fromDate, $toDate, $timezone): void {
                foreach ($chunk as $lead) {
                    $closedAt = $lead->entity_closed_at?->copy()->setTimezone($timezone);
                    if (!$this->dateInRange($closedAt, $fromDate, $toDate)) {
                        continue;
                    }

                    $price = (float) ($lead->raw['price'] ?? 0);
                    if ($price <= 0) {
                        continue;
                    }

                    $target = $this->designerTarget($lead->embedded ?? []);
                    if ($target === null) {
                        continue;
                    }

                    $key = "{$target['type']}:{$target['id']}";
                    $designers[$key] ??= ['type' => $target['type'], 'id' => $target['id'], 'budgetTotal' => 0.0, 'dealCount' => 0];
                    $designers[$key]['budgetTotal'] += $price;
                    $designers[$key]['dealCount']++;
                }
            });

        if ($designers === []) {
            return $empty;
        }

        $contactIds = array_values(array_unique(array_column(
            array_filter($designers, fn (array $d): bool => $d['type'] === 'contacts'),
            'id'
        )));
        $companyIds = array_values(array_unique(array_column(
            array_filter($designers, fn (array $d): bool => $d['type'] === 'companies'),
            'id'
        )));

        $contacts = CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'contacts')
            ->whereIn('external_id', array_map('strval', $contactIds))
            ->get(['external_id', 'name', 'custom_fields_values'])
            ->keyBy(fn (CrmEntitySnapshot $c): string => (string) $c->external_id);

        $companies = CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'companies')
            ->whereIn('external_id', array_map('strval', $companyIds))
            ->get(['external_id', 'name'])
            ->keyBy(fn (CrmEntitySnapshot $c): string => (string) $c->external_id);

        $designerList = [];
        foreach ($designers as $key => $data) {
            $externalId = (string) $data['id'];

            if ($data['type'] === 'contacts') {
                $contact = $contacts->get($externalId);
                $name = $contact?->name ?: "Контакт #{$data['id']}";
                $category = $contact !== null
                    ? ($this->textFieldValue($contact->custom_fields_values ?? [], $categoryFieldId) ?? '-')
                    : '-';
            } else {
                $company = $companies->get($externalId);
                $name = $company?->name ?: "Компания #{$data['id']}";
                $category = '-';
            }

            $designerList[] = [
                'key' => $key,
                'name' => $name,
                'type' => $data['type'],
                'category' => $category,
                'budgetTotal' => round($data['budgetTotal']),
                'dealCount' => $data['dealCount'],
            ];
        }

        usort($designerList, fn (array $a, array $b) => $b['budgetTotal'] <=> $a['budgetTotal']);

        return [
            'summary' => [
                'designerCount' => count($designerList),
                'dealCount' => array_sum(array_column($designerList, 'dealCount')),
                'budgetTotal' => round(array_sum(array_column($designerList, 'budgetTotal'))),
            ],
            'designers' => $designerList,
        ];
    }

    private function wonStatusIds(AmoAccount $account, int $pipelineId): array
    {
        return CrmPipelineStatusSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->when($pipelineId > 0, fn ($q) => $q->where('amo_pipeline_id', $pipelineId))
            ->where('amo_status_id', self::WON_STATUS_ID)
            ->pluck('amo_status_id')
            ->toArray();
    }

    // Designer = the deal's linked contact if present, else its linked company, else null (deal excluded).
    private function designerTarget(?array $embedded): ?array
    {
        $contacts = $embedded['contacts'] ?? [];
        if ($contacts !== []) {
            $main = null;
            foreach ($contacts as $contact) {
                if (($contact['is_main'] ?? false) === true) {
                    $main = $contact;
                    break;
                }
            }
            $main ??= $contacts[0];

            return ['type' => 'contacts', 'id' => (int) $main['id']];
        }

        $companies = $embedded['companies'] ?? [];
        if ($companies !== []) {
            return ['type' => 'companies', 'id' => (int) $companies[0]['id']];
        }

        return null;
    }

    private function parseDesignerKey(string $designerKey): array
    {
        [$type, $id] = array_pad(explode(':', $designerKey, 2), 2, '0');

        return [$type, (int) $id];
    }

    private function designerCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone): string
    {
        return implode(':', [
            'amo_manager_topup_designer_breakdown',
            $account->id,
            $from?->toDateString() ?? 'null',
            $to?->toDateString() ?? 'null',
            data_get($config, 'pipeline_id') ?: 'all',
            $timezone,
        ]);
    }

    private function numericFieldValue(array $customFields, int $fieldId): ?float
    {
        foreach ($customFields as $field) {
            $fId = (int) ($field['field_id'] ?? $field['id'] ?? 0);
            if ($fId === $fieldId) {
                $value = $field['values'][0]['value'] ?? null;
                return $value !== null && $value !== '' ? (float) $value : null;
            }
        }

        return null;
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

    // Parses a date custom field value and returns a Carbon in the given timezone.
    // amoCRM stores date fields as Unix timestamps representing midnight in the account timezone.
    private function dateFieldValue(array $customFields, int $fieldId, string $timezone): ?Carbon
    {
        foreach ($customFields as $field) {
            $fId = (int) ($field['field_id'] ?? $field['id'] ?? 0);
            if ($fId === $fieldId) {
                $value = $field['values'][0]['value'] ?? null;
                if ($value === null || $value === '') {
                    return null;
                }

                return is_numeric($value)
                    ? Carbon::createFromTimestamp((int) $value, $timezone)
                    : Carbon::parse((string) $value, $timezone);
            }
        }

        return null;
    }

    // Compares dates as Y-m-d strings to avoid time-of-day edge cases across timezones.
    // $fromDate and $toDate are already in account timezone (from the controller's period()).
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

    private function cacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, array $selectedManagers, string $timezone): string
    {
        return implode(':', [
            'amo_manager_topup_breakdown',
            $account->id,
            $from?->toDateString() ?? 'null',
            $to?->toDateString() ?? 'null',
            data_get($config, 'pipeline_id') ?: 'all',
            implode(',', $selectedManagers),
            $timezone,
        ]);
    }
}
