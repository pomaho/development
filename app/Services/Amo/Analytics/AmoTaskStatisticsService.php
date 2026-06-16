<?php

namespace App\Services\Amo\Analytics;

use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Models\CrmCustomFieldSnapshot;
use App\Models\CrmEntitySnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class AmoTaskStatisticsService
{
    private const RECRUITER_FIELD_NAME = 'Рекрутер';
    private const MANAGER_FIELD_NAME = 'Менеджер';
    private const TEAM_FIELD_NAME = 'Команда';
    private const CITY_FIELD_NAME = 'Город';
    private const SOURCE_FIELD_NAME = 'Источник';

    public function statistics(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $users = AmoUsersSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->get()
            ->keyBy('amo_user_id');
        $rows = [];
        $seenTaskIdsByUser = [];

        CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'events')
            ->where('name', 'task_completed')
            ->when($from, fn ($query) => $query->where('entity_created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('entity_created_at', '<=', $to))
            ->orderBy('id')
            ->chunkById(500, function ($events) use (&$rows, &$seenTaskIdsByUser, $users): void {
                foreach ($events as $event) {
                    $userId = (int) ($event->responsible_user_id ?? 0);
                    $taskId = (string) data_get($event->embedded, 'entity_id', '');

                    if ($userId <= 0 || $taskId === '') {
                        continue;
                    }

                    if (isset($seenTaskIdsByUser[$userId][$taskId])) {
                        continue;
                    }

                    $seenTaskIdsByUser[$userId][$taskId] = true;

                    $rows[$userId] ??= [
                        'responsible_user_id' => $userId,
                        'responsible_name' => $users->get($userId)?->name,
                        'completed_count' => 0,
                        'completed_overdue_count' => 0,
                        'open_count' => 0,
                        'open_overdue_count' => 0,
                        'overdue_count' => 0,
                        'total_count' => 0,
                    ];

                    $rows[$userId]['completed_count']++;
                    $rows[$userId]['total_count']++;
                }
            });

        return collect($rows)
            ->map(function (array $row): array {
                $row['overdue_rate'] = $row['total_count'] > 0
                    ? round($row['overdue_count'] / $row['total_count'] * 100, 1)
                    : 0.0;

                return $row;
            })
            ->sortByDesc('total_count')
            ->values()
            ->all();
    }

    public function completedOverdueDashboard(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return Cache::remember(
            $this->dashboardCacheKey($account, $from, $to),
            now()->addMinutes(10),
            fn (): array => $this->buildCompletedOverdueDashboard($account, $from, $to),
        );
    }

    public function refreshDashboardCacheVersion(AmoAccount $account): void
    {
        Cache::put($this->dashboardCacheVersionKey($account), (string) now()->getPreciseTimestamp(6), now()->addDays(2));
    }

    public function recruiterLeadDistribution(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = []): array
    {
        return Cache::remember(
            $this->recruiterLeadDistributionCacheKey($account, $from, $to, $config),
            now()->addMinutes(10),
            fn (): array => $this->buildRecruiterLeadDistribution($account, $from, $to, $config),
        );
    }

    public function recruiterTeamCityBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = []): array
    {
        return Cache::remember(
            $this->recruiterTeamCityBreakdownCacheKey($account, $from, $to, $config),
            now()->addMinutes(10),
            fn (): array => $this->buildRecruiterTeamCityBreakdown($account, $from, $to, $config),
        );
    }

    public function recruiterLeadDistributionDiagnostics(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = []): array
    {
        $fieldId = (int) data_get($config, 'recruiter_field_id', 0);
        $fieldName = (string) (data_get($config, 'recruiter_field_name') ?: self::RECRUITER_FIELD_NAME);
        $managerFieldId = (int) data_get($config, 'manager_field_id', 0);
        $managerFieldName = (string) (data_get($config, 'manager_field_name') ?: self::MANAGER_FIELD_NAME);
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $pipelineName = data_get($config, 'pipeline_name');
        $fieldQuery = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');
        $field = $fieldId > 0
            ? (clone $fieldQuery)->where('amo_field_id', $fieldId)->first()
            : (clone $fieldQuery)->where('name', $fieldName)->first();
        $enumIdsByValue = collect($field?->enums ?? [])
            ->filter(fn (array $enum): bool => isset($enum['id']) && isset($enum['value']))
            ->mapWithKeys(fn (array $enum): array => [$this->normaliseRecruiterValue($enum['value']) => (int) $enum['id']])
            ->all();
        $baseLeadsQuery = CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');
        $periodLeadsQuery = (clone $baseLeadsQuery)
            ->when($from, fn ($query) => $query->where('entity_created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('entity_created_at', '<=', $to));
        $pipelineLeadsQuery = (clone $periodLeadsQuery)
            ->when($pipelineId > 0, fn ($query) => $query->where('pipeline_id', $pipelineId));
        $pipelineAllTimeQuery = (clone $baseLeadsQuery)
            ->when($pipelineId > 0, fn ($query) => $query->where('pipeline_id', $pipelineId));
        $fieldValues = [];
        $sampleLeads = [];
        $leadsWithField = 0;
        $assignedLeads = 0;
        $scannedLeads = 0;

        $pipelineLeadsQuery
            ->orderBy('id')
            ->chunkById(500, function ($leads) use (&$fieldValues, &$sampleLeads, &$leadsWithField, &$assignedLeads, &$scannedLeads, $field, $fieldName, $enumIdsByValue): void {
                foreach ($leads as $lead) {
                    $scannedLeads++;

                    if ($field === null) {
                        continue;
                    }

                    $values = $this->recruiterFieldValues($lead->custom_fields_values ?? [], (int) $field->amo_field_id, $fieldName, $enumIdsByValue);

                    if ($values === []) {
                        continue;
                    }

                    $leadsWithField++;

                    if (collect($values)->contains(fn (array $value): bool => (int) $value['enum_id'] > 0)) {
                        $assignedLeads++;
                    }

                    foreach ($values as $value) {
                        $key = (int) $value['enum_id'] > 0
                            ? 'enum:'.$value['enum_id']
                            : 'value:'.$this->normaliseRecruiterValue($value['value']);
                        $fieldValues[$key] ??= [
                            'enum_id' => (int) $value['enum_id'] ?: null,
                            'value' => $value['value'],
                            'count' => 0,
                            'matched_enum' => (int) $value['enum_id'] > 0,
                        ];
                        $fieldValues[$key]['count']++;
                    }

                    if (count($sampleLeads) < 10) {
                        $sampleLeads[] = [
                            'id' => $lead->external_id,
                            'name' => $lead->name,
                            'pipeline_id' => $lead->pipeline_id,
                            'status_id' => $lead->status_id,
                            'created_at' => $lead->entity_created_at?->toDateTimeString(),
                            'field_values' => $values,
                        ];
                    }
                }
            });

        return [
            'pipeline_id' => $pipelineId ?: null,
            'pipeline_name' => $pipelineName,
            'field_id' => $field?->amo_field_id,
            'field_name' => $field?->name ?? $fieldName,
            'field_found' => $field !== null,
            'field_type' => $field?->field_type,
            'field_enum_count' => count($field?->enums ?? []),
            'synced_leads_total' => (clone $baseLeadsQuery)->count(),
            'period_leads_total' => (clone $periodLeadsQuery)->count(),
            'pipeline_leads_total' => (clone $pipelineAllTimeQuery)->count(),
            'pipeline_period_leads_total' => $scannedLeads,
            'pipeline_first_lead_created_at' => (clone $pipelineAllTimeQuery)->min('entity_created_at'),
            'pipeline_last_lead_created_at' => (clone $pipelineAllTimeQuery)->max('entity_created_at'),
            'leads_with_field' => $leadsWithField,
            'assigned_leads' => $assignedLeads,
            'field_values' => collect($fieldValues)->sortByDesc('count')->values()->all(),
            'sample_leads' => $sampleLeads,
        ];
    }

    private function buildRecruiterLeadDistribution(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = []): array
    {
        $fieldId = (int) data_get($config, 'recruiter_field_id', 0);
        $fieldName = (string) (data_get($config, 'recruiter_field_name') ?: self::RECRUITER_FIELD_NAME);
        $managerFieldId = (int) data_get($config, 'manager_field_id', 0);
        $managerFieldName = (string) (data_get($config, 'manager_field_name') ?: self::MANAGER_FIELD_NAME);
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $pipelineName = data_get($config, 'pipeline_name');
        $fieldQuery = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');
        $field = $fieldId > 0
            ? (clone $fieldQuery)->where('amo_field_id', $fieldId)->first()
            : (clone $fieldQuery)->where('name', $fieldName)->first();
        $enums = collect($field?->enums ?? [])
            ->filter(fn (array $enum): bool => isset($enum['id']) && isset($enum['value']))
            ->mapWithKeys(fn (array $enum): array => [(int) $enum['id'] => [
                'enum_id' => (int) $enum['id'],
                'name' => (string) $enum['value'],
                'leads_count' => 0,
                'transferred_to_manager_count' => 0,
            ]])
            ->all();
        $enumIdsByValue = collect($field?->enums ?? [])
            ->filter(fn (array $enum): bool => isset($enum['id']) && isset($enum['value']))
            ->mapWithKeys(fn (array $enum): array => [$this->normaliseRecruiterValue($enum['value']) => (int) $enum['id']])
            ->all();
        $managerField = $managerFieldId > 0
            ? (clone $fieldQuery)->where('amo_field_id', $managerFieldId)->first()
            : (clone $fieldQuery)->where('name', $managerFieldName)->first();
        $managerEnumIdsByValue = collect($managerField?->enums ?? [])
            ->filter(fn (array $enum): bool => isset($enum['id']) && isset($enum['value']))
            ->mapWithKeys(fn (array $enum): array => [$this->normaliseRecruiterValue($enum['value']) => (int) $enum['id']])
            ->all();

        if ($field === null) {
            return [
                'field_name' => $fieldName,
                'field_id' => null,
                'field_found' => false,
                'manager_field_name' => $managerFieldName,
                'manager_field_id' => null,
                'manager_field_found' => false,
                'pipeline_id' => $pipelineId ?: null,
                'pipeline_name' => $pipelineName,
                'total_leads_count' => 0,
                'assigned_leads_count' => 0,
                'transferred_to_manager_count' => 0,
                'recruiters' => [],
            ];
        }

        $leadIdsByEnum = [];
        $transferredLeadIdsByEnum = [];
        $totalLeads = 0;

        CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($query) => $query->where('pipeline_id', $pipelineId))
            ->orderBy('id')
            ->chunkById(500, function ($leads) use (&$leadIdsByEnum, &$transferredLeadIdsByEnum, &$totalLeads, $field, $fieldName, $enumIdsByValue, $managerField, $managerFieldName, $managerEnumIdsByValue, $from, $to): void {
                foreach ($leads as $lead) {
                    if (! $this->inPeriod($lead->entity_created_at, $from, $to)) {
                        continue;
                    }

                    $totalLeads++;
                    $leadId = (string) $lead->external_id;
                    $hasManager = $managerField !== null
                        && $this->fieldHasAnyValue($lead->custom_fields_values ?? [], (int) $managerField->amo_field_id, $managerFieldName, $managerEnumIdsByValue);

                    foreach ($this->recruiterEnumIds($lead->custom_fields_values ?? [], (int) $field->amo_field_id, $fieldName, $enumIdsByValue) as $enumId) {
                        $leadIdsByEnum[$enumId][$leadId] = true;

                        if ($hasManager) {
                            $transferredLeadIdsByEnum[$enumId][$leadId] = true;
                        }
                    }
                }
            });

        foreach ($leadIdsByEnum as $enumId => $leadIds) {
            $enums[$enumId] ??= [
                'enum_id' => $enumId,
                'name' => "Значение {$enumId}",
                'leads_count' => 0,
                'transferred_to_manager_count' => 0,
            ];
            $enums[$enumId]['leads_count'] = count($leadIds);
            $enums[$enumId]['transferred_to_manager_count'] = count($transferredLeadIdsByEnum[$enumId] ?? []);
        }

        $rows = collect($enums)
            ->sortByDesc('leads_count')
            ->values()
            ->all();

        return [
            'field_name' => $field->name,
            'field_id' => (int) $field->amo_field_id,
            'field_found' => true,
            'manager_field_name' => $managerField?->name ?? $managerFieldName,
            'manager_field_id' => $managerField?->amo_field_id,
            'manager_field_found' => $managerField !== null,
            'pipeline_id' => $pipelineId ?: null,
            'pipeline_name' => $pipelineName,
            'total_leads_count' => $totalLeads,
            'assigned_leads_count' => collect($leadIdsByEnum)
                ->flatMap(fn (array $leadIds): array => array_keys($leadIds))
                ->unique()
                ->count(),
            'transferred_to_manager_count' => collect($transferredLeadIdsByEnum)
                ->flatMap(fn (array $leadIds): array => array_keys($leadIds))
                ->unique()
                ->count(),
            'recruiters' => $rows,
        ];
    }

    private function buildRecruiterTeamCityBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = []): array
    {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $pipelineName = data_get($config, 'pipeline_name');
        $fieldQuery = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');
        $recruiterField = $this->leadField($fieldQuery, (int) data_get($config, 'recruiter_field_id', 0), (string) (data_get($config, 'recruiter_field_name') ?: self::RECRUITER_FIELD_NAME));
        $managerField = $this->leadField($fieldQuery, (int) data_get($config, 'manager_field_id', 0), (string) (data_get($config, 'manager_field_name') ?: self::MANAGER_FIELD_NAME));
        $teamField = $this->leadField($fieldQuery, (int) data_get($config, 'team_field_id', 0), (string) (data_get($config, 'team_field_name') ?: self::TEAM_FIELD_NAME));
        $cityField = $this->leadField($fieldQuery, (int) data_get($config, 'city_field_id', 0), (string) (data_get($config, 'city_field_name') ?: self::CITY_FIELD_NAME));
        $sourceField = $this->leadField($fieldQuery, (int) data_get($config, 'source_field_id', 0), (string) (data_get($config, 'source_field_name') ?: self::SOURCE_FIELD_NAME));

        if ($recruiterField === null || $managerField === null || $teamField === null || $cityField === null) {
            return [
                'pipeline_id' => $pipelineId ?: null,
                'pipeline_name' => $pipelineName,
                'recruiter_field_found' => $recruiterField !== null,
                'manager_field_found' => $managerField !== null,
                'team_field_found' => $teamField !== null,
                'city_field_found' => $cityField !== null,
                'source_field_found' => $sourceField !== null,
                'team_field_name' => $teamField?->name ?? self::TEAM_FIELD_NAME,
                'city_field_name' => $cityField?->name ?? self::CITY_FIELD_NAME,
                'source_field_name' => $sourceField?->name ?? self::SOURCE_FIELD_NAME,
                'total_leads_count' => 0,
                'source_columns' => [],
                'recruiters' => [],
            ];
        }

        $recruiterNames = $this->enumNamesById($recruiterField);
        $recruiterEnumIdsByValue = $this->enumIdsByValue($recruiterField);
        $managerEnumIdsByValue = $this->enumIdsByValue($managerField);
        $teamEnumIdsByValue = $this->enumIdsByValue($teamField);
        $cityEnumIdsByValue = $this->enumIdsByValue($cityField);
        $sourceEnumIdsByValue = $sourceField !== null ? $this->enumIdsByValue($sourceField) : [];
        $sourceColumns = $sourceField !== null
            ? collect($sourceField->enums ?? [])
                ->filter(fn (array $enum): bool => isset($enum['value']))
                ->map(fn (array $enum): string => (string) $enum['value'])
                ->filter(fn (string $value): bool => trim($value) !== '')
                ->unique()
                ->values()
                ->all()
            : [];
        $rows = [];
        $totalLeads = 0;

        CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($query) => $query->where('pipeline_id', $pipelineId))
            ->orderBy('id')
            ->chunkById(500, function ($leads) use (&$rows, &$totalLeads, &$sourceColumns, $from, $to, $recruiterField, $managerField, $teamField, $cityField, $sourceField, $recruiterNames, $recruiterEnumIdsByValue, $managerEnumIdsByValue, $teamEnumIdsByValue, $cityEnumIdsByValue, $sourceEnumIdsByValue): void {
                foreach ($leads as $lead) {
                    if (! $this->inPeriod($lead->entity_created_at, $from, $to)) {
                        continue;
                    }

                    $customFields = $lead->custom_fields_values ?? [];

                    if (! $this->fieldHasAnyValue($customFields, (int) $managerField->amo_field_id, $managerField->name, $managerEnumIdsByValue)) {
                        continue;
                    }

                    $recruiterIds = $this->recruiterEnumIds($customFields, (int) $recruiterField->amo_field_id, $recruiterField->name, $recruiterEnumIdsByValue);

                    if ($recruiterIds === []) {
                        continue;
                    }

                    $teamValues = $this->fieldValueLabels($customFields, (int) $teamField->amo_field_id, $teamField->name, $teamEnumIdsByValue);
                    $cityValues = $this->fieldValueLabels($customFields, (int) $cityField->amo_field_id, $cityField->name, $cityEnumIdsByValue);
                    $sourceValues = $sourceField !== null
                        ? $this->fieldValueLabels($customFields, (int) $sourceField->amo_field_id, $sourceField->name, $sourceEnumIdsByValue)
                        : [];

                    if ($teamValues === [] || $cityValues === []) {
                        continue;
                    }

                    $totalLeads++;

                    foreach ($recruiterIds as $recruiterId) {
                        $rows[$recruiterId] ??= [
                            'enum_id' => $recruiterId,
                            'name' => $recruiterNames[$recruiterId] ?? "Значение {$recruiterId}",
                            'total_leads_count' => 0,
                            'teams' => [],
                        ];
                        $rows[$recruiterId]['total_leads_count']++;

                        foreach ($teamValues as $teamValue) {
                            $rows[$recruiterId]['teams'][$teamValue] ??= [
                                'name' => $teamValue,
                                'total_leads_count' => 0,
                                'cities' => [],
                            ];
                            $rows[$recruiterId]['teams'][$teamValue]['total_leads_count']++;

                            foreach ($cityValues as $cityValue) {
                                $rows[$recruiterId]['teams'][$teamValue]['cities'][$cityValue] ??= [
                                    'name' => $cityValue,
                                    'leads_count' => 0,
                                    'sources' => [],
                                ];
                                $rows[$recruiterId]['teams'][$teamValue]['cities'][$cityValue]['leads_count']++;

                                foreach ($sourceValues as $sourceValue) {
                                    if (! in_array($sourceValue, $sourceColumns, true)) {
                                        $sourceColumns[] = $sourceValue;
                                    }

                                    $rows[$recruiterId]['teams'][$teamValue]['cities'][$cityValue]['sources'][$sourceValue] ??= 0;
                                    $rows[$recruiterId]['teams'][$teamValue]['cities'][$cityValue]['sources'][$sourceValue]++;
                                }
                            }
                        }
                    }
                }
            });

        $sourceColumns = collect($sourceColumns)
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->unique()
            ->values()
            ->all();
        $recruiters = collect($rows)
            ->map(function (array $recruiter) use ($sourceColumns): array {
                $recruiter['teams'] = collect($recruiter['teams'])
                    ->map(function (array $team) use ($sourceColumns): array {
                        $team['cities'] = collect($team['cities'])
                            ->map(function (array $city) use ($sourceColumns): array {
                                $city['sources'] = collect($sourceColumns)
                                    ->mapWithKeys(fn (string $source): array => [$source => (int) ($city['sources'][$source] ?? 0)])
                                    ->all();

                                return $city;
                            })
                            ->sortByDesc('leads_count')
                            ->values()
                            ->all();

                        return $team;
                    })
                    ->sortByDesc('total_leads_count')
                    ->values()
                    ->all();

                return $recruiter;
            })
            ->sortByDesc('total_leads_count')
            ->values()
            ->all();

        return [
            'pipeline_id' => $pipelineId ?: null,
            'pipeline_name' => $pipelineName,
            'recruiter_field_found' => true,
            'manager_field_found' => true,
            'team_field_found' => true,
            'city_field_found' => true,
            'source_field_found' => $sourceField !== null,
            'team_field_name' => $teamField->name,
            'city_field_name' => $cityField->name,
            'source_field_name' => $sourceField?->name ?? self::SOURCE_FIELD_NAME,
            'total_leads_count' => $totalLeads,
            'source_columns' => $sourceColumns,
            'recruiters' => $recruiters,
        ];
    }

    private function recruiterLeadDistributionCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_recruiter_lead_distribution',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'recruiter_field_id') ?: data_get($config, 'recruiter_field_name', self::RECRUITER_FIELD_NAME),
            data_get($config, 'manager_field_id') ?: data_get($config, 'manager_field_name', self::MANAGER_FIELD_NAME),
        ]);
    }

    private function recruiterTeamCityBreakdownCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_recruiter_team_city_breakdown',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'recruiter_field_id') ?: data_get($config, 'recruiter_field_name', self::RECRUITER_FIELD_NAME),
            data_get($config, 'manager_field_id') ?: data_get($config, 'manager_field_name', self::MANAGER_FIELD_NAME),
            data_get($config, 'team_field_id') ?: data_get($config, 'team_field_name', self::TEAM_FIELD_NAME),
            data_get($config, 'city_field_id') ?: data_get($config, 'city_field_name', self::CITY_FIELD_NAME),
            data_get($config, 'source_field_id') ?: data_get($config, 'source_field_name', self::SOURCE_FIELD_NAME),
        ]);
    }

    private function recruiterEnumIds(array $customFields, int $fieldId, string $fieldName, array $enumIdsByValue): array
    {
        $enumIds = [];

        foreach ($this->recruiterFieldValues($customFields, $fieldId, $fieldName, $enumIdsByValue) as $value) {
            $enumId = (int) $value['enum_id'];

            if ($enumId > 0) {
                $enumIds[$enumId] = true;
            }
        }

        return array_keys($enumIds);
    }

    private function recruiterFieldValues(array $customFields, int $fieldId, string $fieldName, array $enumIdsByValue): array
    {
        $fieldValues = [];

        foreach ($customFields as $customField) {
            $currentFieldId = (int) ($customField['field_id'] ?? $customField['id'] ?? 0);
            $currentFieldName = (string) ($customField['field_name'] ?? $customField['name'] ?? '');

            if ($currentFieldId !== $fieldId && $currentFieldName !== $fieldName) {
                continue;
            }

            foreach (($customField['values'] ?? []) as $value) {
                $enumId = (int) ($value['enum_id'] ?? $value['enum'] ?? 0);

                if ($enumId <= 0 && isset($value['value'])) {
                    $enumId = $enumIdsByValue[$this->normaliseRecruiterValue($value['value'])] ?? 0;
                }

                $fieldValues[] = [
                    'enum_id' => $enumId ?: null,
                    'value' => (string) ($value['value'] ?? ''),
                ];
            }
        }

        return $fieldValues;
    }

    private function fieldHasAnyValue(array $customFields, int $fieldId, string $fieldName, array $enumIdsByValue): bool
    {
        return collect($this->recruiterFieldValues($customFields, $fieldId, $fieldName, $enumIdsByValue))
            ->contains(fn (array $value): bool => (int) $value['enum_id'] > 0 || trim((string) $value['value']) !== '');
    }

    private function leadField($fieldQuery, int $fieldId, string $fieldName): ?CrmCustomFieldSnapshot
    {
        return $fieldId > 0
            ? (clone $fieldQuery)->where('amo_field_id', $fieldId)->first()
            : (clone $fieldQuery)->where('name', $fieldName)->first();
    }

    private function enumIdsByValue(CrmCustomFieldSnapshot $field): array
    {
        return collect($field->enums ?? [])
            ->filter(fn (array $enum): bool => isset($enum['id']) && isset($enum['value']))
            ->mapWithKeys(fn (array $enum): array => [$this->normaliseRecruiterValue($enum['value']) => (int) $enum['id']])
            ->all();
    }

    private function enumNamesById(CrmCustomFieldSnapshot $field): array
    {
        return collect($field->enums ?? [])
            ->filter(fn (array $enum): bool => isset($enum['id']) && isset($enum['value']))
            ->mapWithKeys(fn (array $enum): array => [(int) $enum['id'] => (string) $enum['value']])
            ->all();
    }

    private function fieldValueLabels(array $customFields, int $fieldId, string $fieldName, array $enumIdsByValue): array
    {
        return collect($this->recruiterFieldValues($customFields, $fieldId, $fieldName, $enumIdsByValue))
            ->map(fn (array $value): string => trim((string) $value['value']))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function normaliseRecruiterValue(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function buildCompletedOverdueDashboard(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $users = AmoUsersSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->get()
            ->keyBy('amo_user_id');
        $rows = [];

        CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->orderBy('id')
            ->chunkById(500, function ($tasks) use (&$rows, $users, $from, $to): void {
                foreach ($tasks as $task) {
                    $raw = $task->raw ?? [];
                    $responsibleId = (int) ($task->responsible_user_id ?? 0);
                    $user = $users->get($responsibleId);

                    if ($responsibleId <= 0 || $user === null || ! (bool) ($raw['is_completed'] ?? false)) {
                        continue;
                    }

                    $completedAt = $this->completionTime($raw) ?? $task->entity_updated_at;
                    $completeTill = $this->timestamp($raw['complete_till'] ?? null);

                    if (! $this->inPeriod($completedAt, $from, $to)) {
                        continue;
                    }

                    $groupId = $user->group_id ? (int) $user->group_id : 0;
                    $rows[$groupId] ??= [
                        'group_id' => $groupId ?: null,
                        'group_name' => $this->groupName($user),
                        'users' => [],
                    ];
                    $rows[$groupId]['users'][$responsibleId] ??= [
                        'id' => $responsibleId,
                        'name' => $user->name,
                        'completed_count' => 0,
                        'completed_overdue_count' => 0,
                        'overdue_rate' => 0.0,
                    ];

                    $rows[$groupId]['users'][$responsibleId]['completed_count']++;

                    if ($completeTill !== null && $completedAt !== null && $completedAt->greaterThan($completeTill)) {
                        $rows[$groupId]['users'][$responsibleId]['completed_overdue_count']++;
                    }
                }
            });

        return collect($rows)
            ->map(function (array $group): array {
                $group['users'] = collect($group['users'])
                    ->map(function (array $user): array {
                        $user['overdue_rate'] = $user['completed_count'] > 0
                            ? round($user['completed_overdue_count'] / $user['completed_count'] * 100, 1)
                            : 0.0;

                        return $user;
                    })
                    ->sortByDesc('completed_overdue_count')
                    ->values()
                    ->all();

                $group['completed_count'] = collect($group['users'])->sum('completed_count');
                $group['completed_overdue_count'] = collect($group['users'])->sum('completed_overdue_count');

                return $group;
            })
            ->sortBy('group_name')
            ->values()
            ->all();
    }

    private function dashboardCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_task_overdue_dashboard',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
        ]);
    }

    private function dashboardCacheVersionKey(AmoAccount $account): string
    {
        return "amo_task_overdue_dashboard_version:{$account->id}";
    }

    private function inPeriod(?Carbon $date, ?Carbon $from, ?Carbon $to): bool
    {
        if ($date === null) {
            return false;
        }

        if ($from !== null && $date->lt($from)) {
            return false;
        }

        if ($to !== null && $date->gt($to)) {
            return false;
        }

        return true;
    }

    private function timestamp(mixed $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    private function completionTime(array $raw): ?Carbon
    {
        return $this->timestamp(data_get($raw, '_task_statistics.completed_at'));
    }

    private function groupName(AmoUsersSnapshot $user): string
    {
        return data_get($user->raw, '_embedded.group.name')
            ?: data_get($user->raw, 'group.name')
            ?: ($user->group_id ? "Группа {$user->group_id}" : 'Без группы');
    }

}
