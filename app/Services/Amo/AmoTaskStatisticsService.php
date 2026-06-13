<?php

namespace App\Services\Amo;

use App\Services\Amo\Client\AmoFallbackHttpClient;

use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Models\CrmCustomFieldSnapshot;
use App\Models\CrmEntitySnapshot;
use App\Models\TaskStatisticsSyncRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class AmoTaskStatisticsService
{
    private const RECRUITER_FIELD_NAME = 'Рекрутер';
    private const MANAGER_FIELD_NAME = 'Менеджер';
    private const TEAM_FIELD_NAME = 'Команда';
    private const CITY_FIELD_NAME = 'Город';
    private const SOURCE_FIELD_NAME = 'Источник';

    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function sync(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, ?TaskStatisticsSyncRun $run = null): array
    {
        $syncedAt = now();
        $run?->forceFill([
            'status' => TaskStatisticsSyncRun::STATUS_RUNNING,
            'started_at' => now(),
        ])->save();

        $completed = $this->syncTaskQuery($account, [
            'filter[is_completed]' => 1,
            ...$this->updatedAtQuery($from, $to),
        ], $syncedAt, $run, 'completed');
        $completionEvents = $this->syncCompletionEvents($account, $from, $to, $run);
        $open = $this->syncTaskQuery($account, [
            'filter[is_completed]' => 0,
        ], $syncedAt, $run, 'open');
        $events = $this->syncEvents($account, $from, $to, $syncedAt);

        $run?->forceFill([
            'status' => TaskStatisticsSyncRun::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();
        $this->refreshDashboardCacheVersion($account);

        return [
            'completed' => $completed,
            'completion_events' => $completionEvents,
            'open' => $open,
            'events' => $events,
        ];
    }

    public function statistics(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $users = AmoUsersSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->get()
            ->keyBy('amo_user_id');
        $rows = [];
        $now = now();

        CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->orderBy('id')
            ->chunkById(500, function ($tasks) use (&$rows, $users, $from, $to, $now): void {
                foreach ($tasks as $task) {
                    $raw = $task->raw ?? [];
                    $responsibleId = (int) ($task->responsible_user_id ?? 0);

                    if ($responsibleId <= 0) {
                        continue;
                    }

                    $rows[$responsibleId] ??= [
                        'responsible_user_id' => $responsibleId,
                        'responsible_name' => $users->get($responsibleId)?->name,
                        'completed_count' => 0,
                        'completed_overdue_count' => 0,
                        'open_count' => 0,
                        'open_overdue_count' => 0,
                        'overdue_count' => 0,
                        'total_count' => 0,
                    ];

                    $isCompleted = (bool) ($raw['is_completed'] ?? false);
                    $completeTill = $this->timestamp($raw['complete_till'] ?? null);
                    $updatedAt = $task->entity_updated_at;
                    $completedAt = $this->completionTime($raw) ?? $updatedAt;
                    $completedLate = $isCompleted && $completeTill !== null && $completedAt !== null && $completedAt->greaterThan($completeTill);

                    if ($isCompleted && $this->inPeriod($completedAt, $from, $to)) {
                        $rows[$responsibleId]['completed_count']++;
                        $rows[$responsibleId]['total_count']++;

                        if ($completedLate) {
                            $rows[$responsibleId]['completed_overdue_count']++;
                            $rows[$responsibleId]['overdue_count']++;
                        }
                    }

                    if (! $isCompleted) {
                        $rows[$responsibleId]['open_count']++;
                        $rows[$responsibleId]['total_count']++;

                        if ($completeTill !== null && $completeTill->lessThan($now)) {
                            $rows[$responsibleId]['open_overdue_count']++;
                            $rows[$responsibleId]['overdue_count']++;
                        }
                    }
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

    private function syncTaskQuery(AmoAccount $account, array $query, Carbon $syncedAt, ?TaskStatisticsSyncRun $run, string $type): int
    {
        $page = 1;
        $total = 0;

        do {
            $payload = $this->http->get($account, '/api/v4/tasks', [...$query, 'page' => $page, 'limit' => 250]);
            $tasks = $payload['_embedded']['tasks'] ?? [];
            $tasks = is_array($tasks) ? $tasks : [];

            foreach ($tasks as $task) {
                $this->saveTask($account, $task, $syncedAt);
            }

            $count = count($tasks);
            $total += $count;
            $this->updateRunProgress($run, $type, $count);

            $currentPage = (int) ($payload['_page'] ?? $page);
            $pageCount = (int) ($payload['_page_count'] ?? 0);
            $hasNext = isset($payload['_links']['next']['href']);
            $page++;

            if ($hasNext) {
                usleep(160000);
            }
        } while (($pageCount > 0 && $currentPage < $pageCount) || ($pageCount === 0 && $hasNext));

        return $total;
    }

    private function syncCompletionEvents(AmoAccount $account, ?Carbon $from, ?Carbon $to, ?TaskStatisticsSyncRun $run): int
    {
        $page = 1;
        $total = 0;
        $eventStatsByTaskId = [];
        $query = [
            'filter[type][]' => 'task_completed',
            'filter[entity][]' => 'task',
            ...$this->createdAtQuery($from, $to),
        ];

        do {
            $payload = $this->http->get($account, '/api/v4/events', [...$query, 'page' => $page, 'limit' => 250]);
            $events = $payload['_embedded']['events'] ?? [];
            $events = is_array($events) ? $events : [];

            foreach ($events as $event) {
                $taskId = (int) ($event['entity_id'] ?? 0);

                if ($taskId <= 0) {
                    continue;
                }

                $eventCompletedAt = (int) ($event['created_at'] ?? 0);
                $currentCompletedAt = (int) ($eventStatsByTaskId[$taskId]['completed_at'] ?? 0);

                if ($eventCompletedAt > 0 && ($currentCompletedAt === 0 || $eventCompletedAt < $currentCompletedAt)) {
                    $eventStatsByTaskId[$taskId] = $this->completionStatsFromEvent($event);
                }
            }

            $count = count($events);
            $total += $count;
            $this->updateRunProgress($run, 'completion_events', $count);

            $currentPage = (int) ($payload['_page'] ?? $page);
            $pageCount = (int) ($payload['_page_count'] ?? 0);
            $hasNext = isset($payload['_links']['next']['href']);
            $page++;

            if ($hasNext) {
                usleep(160000);
            }
        } while (($pageCount > 0 && $currentPage < $pageCount) || ($pageCount === 0 && $hasNext));

        $this->syncTasksByIds($account, $eventStatsByTaskId, now());

        return $total;
    }

    private function syncTasksByIds(AmoAccount $account, array $eventStatsByTaskId, Carbon $syncedAt): void
    {
        foreach (array_chunk($eventStatsByTaskId, 250, true) as $statsChunk) {
            if ($statsChunk === []) {
                continue;
            }

            $payload = $this->http->get($account, '/api/v4/tasks', [
                'filter[id]' => array_keys($statsChunk),
                'page' => 1,
                'limit' => 250,
            ]);
            $tasks = $payload['_embedded']['tasks'] ?? [];
            $tasks = is_array($tasks) ? $tasks : [];

            foreach ($tasks as $task) {
                $taskId = (int) ($task['id'] ?? 0);

                if (isset($statsChunk[$taskId])) {
                    $task['_task_statistics'] = $statsChunk[$taskId];
                }

                $this->saveTask($account, $task, $syncedAt);
            }

            usleep(160000);
        }
    }

    private function syncEvents(AmoAccount $account, ?Carbon $from, ?Carbon $to, Carbon $syncedAt): int
    {
        $page = 1;
        $total = 0;
        $query = $this->createdAtQuery($from, $to);

        do {
            $payload = $this->http->get($account, '/api/v4/events', [...$query, 'page' => $page, 'limit' => 250]);
            $events = $payload['_embedded']['events'] ?? [];
            $events = is_array($events) ? $events : [];

            foreach ($events as $event) {
                $this->saveEvent($account, $event, $syncedAt);
            }

            $count = count($events);
            $total += $count;

            $currentPage = (int) ($payload['_page'] ?? $page);
            $pageCount = (int) ($payload['_page_count'] ?? 0);
            $hasNext = isset($payload['_links']['next']['href']);
            $page++;

            if ($hasNext) {
                usleep(160000);
            }
        } while (($pageCount > 0 && $currentPage < $pageCount) || ($pageCount === 0 && $hasNext));

        return $total;
    }

    private function saveEvent(AmoAccount $account, array $event, Carbon $syncedAt): void
    {
        CrmEntitySnapshot::query()->updateOrCreate(
            ['amo_account_id' => $account->id, 'entity_type' => 'events', 'external_id' => (string) ($event['id'] ?? md5(json_encode($event)))],
            [
                'name' => $event['type'] ?? 'event',
                'responsible_user_id' => $event['created_by'] ?? null,
                'entity_created_at' => $this->timestamp($event['created_at'] ?? null),
                'entity_updated_at' => $this->timestamp($event['created_at'] ?? null),
                'embedded' => [
                    'entity_id' => $event['entity_id'] ?? null,
                    'entity_type' => $event['entity_type'] ?? $event['entity'] ?? null,
                ],
                'raw' => $event,
                'synced_at' => $syncedAt,
            ]
        );
    }

    private function updateRunProgress(?TaskStatisticsSyncRun $run, string $type, int $count): void
    {
        if ($run === null || $count === 0) {
            return;
        }

        $foundColumn = "{$type}_found";
        $syncedColumn = "{$type}_synced";
        $run->increment($foundColumn, $count);
        $run->increment($syncedColumn, $count);
        $run->refresh();
    }

    private function saveTask(AmoAccount $account, array $task, Carbon $syncedAt): void
    {
        $existing = CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->where('external_id', (string) $task['id'])
            ->first();
        $existingStats = $existing?->raw['_task_statistics'] ?? null;

        if ($existingStats !== null) {
            $task['_task_statistics'] = $existingStats;
        }

        CrmEntitySnapshot::query()->updateOrCreate(
            ['amo_account_id' => $account->id, 'entity_type' => 'tasks', 'external_id' => (string) $task['id']],
            [
                'name' => $this->previewText($task['text'] ?? null),
                'responsible_user_id' => $task['responsible_user_id'] ?? null,
                'entity_created_at' => $this->timestamp($task['created_at'] ?? null),
                'entity_updated_at' => $this->timestamp($task['updated_at'] ?? null),
                'embedded' => [
                    'entity_id' => $task['entity_id'] ?? null,
                    'entity_type' => $task['entity_type'] ?? null,
                ],
                'raw' => $task,
                'synced_at' => $syncedAt,
            ]
        );
    }

    private function completionStatsFromEvent(array $event): array
    {
        return [
            'completed_at' => (int) ($event['created_at'] ?? 0),
            'completed_by' => $event['created_by'] ?? null,
            'completed_event_id' => $event['id'] ?? null,
            'completed_event' => $event,
        ];
    }

    private function updatedAtQuery(?Carbon $from, ?Carbon $to): array
    {
        return array_filter([
            'filter[updated_at][from]' => $from?->timestamp,
            'filter[updated_at][to]' => $to?->timestamp,
        ], fn ($value) => $value !== null);
    }

    private function createdAtQuery(?Carbon $from, ?Carbon $to): array
    {
        return array_filter([
            'filter[created_at][from]' => $from?->timestamp,
            'filter[created_at][to]' => $to?->timestamp,
        ], fn ($value) => $value !== null);
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

    private function previewText(mixed $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?: '');

        return mb_strlen($text) > 250 ? mb_substr($text, 0, 247).'...' : $text;
    }

    private function groupName(AmoUsersSnapshot $user): string
    {
        return data_get($user->raw, '_embedded.group.name')
            ?: data_get($user->raw, 'group.name')
            ?: ($user->group_id ? "Группа {$user->group_id}" : 'Без группы');
    }

}
