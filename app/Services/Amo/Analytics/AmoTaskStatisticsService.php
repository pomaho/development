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
    private const PROJECT_FIELD_NAME = 'Проект';
    private const VACANCY_FIELD_NAME = 'Вакансия';
    private const TAKEN_TO_WORK_FIELD_ID = 1435399;  // "Взято в работу"
    private const TRANSFER_DATE_FIELD_ID = 1435403;   // "Дата передачи менеджеру"
    private const MISSING_DATES_LEAD_LIMIT = 300;


    public function statistics(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $users = AmoUsersSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('amo_user_id');
        $now = now();

        $rows = $users->mapWithKeys(fn (AmoUsersSnapshot $user): array => [
            $user->amo_user_id => [
                'responsible_user_id' => $user->amo_user_id,
                'responsible_name' => $user->name,
                'completed_count' => 0,
                'completed_overdue_count' => 0,
                'open_count' => 0,
                'open_overdue_count' => 0,
                'overdue_count' => 0,
                'total_count' => 0,
            ],
        ])->all();

        CrmEntitySnapshot::query()
            ->select(['id', 'responsible_user_id', 'entity_created_at', 'raw'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->orderBy('id')
            ->chunkById(500, function ($tasks) use (&$rows, $users, $from, $to, $now): void {
                foreach ($tasks as $task) {
                    $raw = $task->raw ?? [];
                    $responsibleId = (int) ($task->responsible_user_id ?? 0);

                    if ($responsibleId <= 0 || ! $users->has($responsibleId)) {
                        continue;
                    }

                    $isCompleted = (bool) ($raw['is_completed'] ?? false);
                    $completeTill = $this->timestamp($raw['complete_till'] ?? null);
                    $completedAt = $this->completionTime($raw) ?? $completeTill;

                    if ($isCompleted && $this->inPeriod($task->entity_created_at, $from, $to)) {
                        $rows[$responsibleId]['completed_count']++;
                        $rows[$responsibleId]['total_count']++;

                        if ($completeTill !== null && $completedAt !== null && $completedAt->greaterThan($completeTill)) {
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

        $userRows = collect($rows)
            ->map(function (array $row): array {
                $row['overdue_rate'] = $row['completed_count'] > 0
                    ? round($row['completed_overdue_count'] / $row['completed_count'] * 100, 1)
                    : 0.0;

                return $row;
            })
            ->all();

        $groups = [];

        foreach ($userRows as $responsibleId => $row) {
            $user = $users->get($responsibleId);
            $groupId = $user?->group_id ? (int) $user->group_id : 0;
            $groups[$groupId] ??= [
                'group_id' => $groupId ?: null,
                'group_name' => $user ? $this->groupName($user) : 'Без группы',
                'users' => [],
            ];
            $groups[$groupId]['users'][] = $row;
        }

        return collect($groups)
            ->map(function (array $group): array {
                $group['users'] = collect($group['users'])
                    ->sortByDesc('completed_count')
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

    public function userOverdueTasks(AmoAccount $account, int $userId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $tasks = [];

        CrmEntitySnapshot::query()
            ->select(['id', 'entity_created_at', 'raw', 'name'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'tasks')
            ->where('responsible_user_id', $userId)
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$tasks, $from, $to): void {
                foreach ($chunk as $task) {
                    $raw = $task->raw ?? [];

                    if (! (bool) ($raw['is_completed'] ?? false)) {
                        continue;
                    }

                    if (! $this->inPeriod($task->entity_created_at, $from, $to)) {
                        continue;
                    }

                    $completeTill = $this->timestamp($raw['complete_till'] ?? null);
                    $completedAt = $this->completionTime($raw) ?? $completeTill;

                    if ($completeTill === null || $completedAt === null || ! $completedAt->greaterThan($completeTill)) {
                        continue;
                    }

                    $tasks[] = [
                        'text' => $raw['text'] ?? $task->name,
                        'complete_till' => $completeTill->format('d.m.Y'),
                        'completed_at' => $completedAt->format('d.m.Y'),
                        'days_overdue' => (int) $completedAt->diffInDays($completeTill),
                    ];
                }
            });

        usort($tasks, fn (array $a, array $b): int => $b['days_overdue'] - $a['days_overdue']);

        return $tasks;
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

    public function recruiterLeadDistribution(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        return Cache::remember(
            $this->recruiterLeadDistributionCacheKey($account, $from, $to, $config, $timezone),
            now()->addMinutes(10),
            fn (): array => $this->buildRecruiterLeadDistribution($account, $from, $to, $config, $timezone),
        );
    }

    public function recruiterTeamCityBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        return Cache::remember(
            $this->recruiterTeamCityBreakdownCacheKey($account, $from, $to, $config, $timezone),
            now()->addMinutes(10),
            fn (): array => $this->buildRecruiterTeamCityBreakdown($account, $from, $to, $config, $timezone),
        );
    }

    public function projectCityVacancyBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        return Cache::remember(
            $this->projectCityVacancyBreakdownCacheKey($account, $from, $to, $config, $timezone),
            now()->addMinutes(10),
            fn (): array => $this->buildProjectCityVacancyBreakdown($account, $from, $to, $config, $timezone),
        );
    }

    public function recruiterScheduleBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        return Cache::remember(
            $this->recruiterScheduleBreakdownCacheKey($account, $from, $to, $config, $timezone),
            now()->addMinutes(10),
            fn (): array => $this->buildRecruiterScheduleBreakdown($account, $from, $to, $config, $timezone),
        );
    }

    public function projectCityVacancyLeads(
        AmoAccount $account,
        ?Carbon $from,
        ?Carbon $to,
        array $config,
        string $projectFilter,
        string $cityFilter,
        string $vacancyFilter,
        string $sourceFilter = '',
        string $teamFilter = '',
        int $recruiterEnumId = 0,
        bool $managerRequired = true,
        int $statusId = 0,
        int $limit = 200,
        string $timezone = 'UTC'
    ): array {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $fieldQuery = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');

        $recruiterField = $this->leadField($fieldQuery, (int) data_get($config, 'recruiter_field_id', 0), (string) (data_get($config, 'recruiter_field_name') ?: self::RECRUITER_FIELD_NAME));
        $managerField = $this->leadField($fieldQuery, (int) data_get($config, 'manager_field_id', 0), (string) (data_get($config, 'manager_field_name') ?: self::MANAGER_FIELD_NAME));
        $teamField = $this->leadField($fieldQuery, (int) data_get($config, 'team_field_id', 0), (string) (data_get($config, 'team_field_name') ?: self::TEAM_FIELD_NAME));
        $projectField = $this->leadField($fieldQuery, (int) data_get($config, 'project_field_id', 0), (string) (data_get($config, 'project_field_name') ?: self::PROJECT_FIELD_NAME));
        $cityField = $this->leadField($fieldQuery, (int) data_get($config, 'city_field_id', 0), (string) (data_get($config, 'city_field_name') ?: self::CITY_FIELD_NAME));
        $vacancyField = $this->leadField($fieldQuery, (int) data_get($config, 'vacancy_field_id', 0), (string) (data_get($config, 'vacancy_field_name') ?: self::VACANCY_FIELD_NAME));
        $sourceField = $this->leadField($fieldQuery, (int) data_get($config, 'source_field_id', 0), (string) (data_get($config, 'source_field_name') ?: self::SOURCE_FIELD_NAME));

        $recruiterEnumIdsByValue = $recruiterField ? $this->enumIdsByValue($recruiterField) : [];
        $managerEnumIdsByValue = $managerField ? $this->enumIdsByValue($managerField) : [];
        $teamEnumIdsByValue = $teamField ? $this->enumIdsByValue($teamField) : [];
        $projectEnumIdsByValue = $projectField ? $this->enumIdsByValue($projectField) : [];
        $cityEnumIdsByValue = $cityField ? $this->enumIdsByValue($cityField) : [];
        $vacancyEnumIdsByValue = $vacancyField ? $this->enumIdsByValue($vacancyField) : [];
        $sourceEnumIdsByValue = $sourceField ? $this->enumIdsByValue($sourceField) : [];

        $leads = [];
        $total = 0;
        $useCustomDateFields = (bool) data_get($config, 'use_custom_date_fields', false);
        $transferDateFieldId = (int) data_get($config, 'transfer_date_field_id', self::TRANSFER_DATE_FIELD_ID);
        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'entity_created_at', 'custom_fields_values'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($statusId > 0, fn ($q) => $q->where('status_id', $statusId))
            ->when(!$useCustomDateFields && $from, fn ($q) => $q->where('entity_created_at', '>=', $from))
            ->when(!$useCustomDateFields && $to, fn ($q) => $q->where('entity_created_at', '<=', $to))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$leads, &$total, $limit, $recruiterField, $managerField, $teamField, $projectField, $cityField, $vacancyField, $sourceField, $recruiterEnumIdsByValue, $managerEnumIdsByValue, $teamEnumIdsByValue, $projectEnumIdsByValue, $cityEnumIdsByValue, $vacancyEnumIdsByValue, $sourceEnumIdsByValue, $projectFilter, $cityFilter, $vacancyFilter, $sourceFilter, $teamFilter, $recruiterEnumId, $managerRequired, $useCustomDateFields, $transferDateFieldId, $fromDate, $toDate, $timezone): void {
                foreach ($chunk as $lead) {
                    $customFields = $lead->custom_fields_values ?? [];

                    if ($useCustomDateFields) {
                        $transferDate = $this->effectiveDate($customFields, $transferDateFieldId, $lead->entity_created_at, $timezone);
                        if (!$this->dateInPeriod($transferDate, $fromDate, $toDate)) {
                            continue;
                        }
                    }

                    // Recruiter must be set
                    $leadRecruiterIds = $recruiterField !== null
                        ? $this->recruiterEnumIds($customFields, (int) $recruiterField->amo_field_id, $recruiterField->name, $recruiterEnumIdsByValue)
                        : [];

                    if ($recruiterField !== null && $leadRecruiterIds === []) {
                        continue;
                    }

                    if ($recruiterEnumId > 0 && !in_array($recruiterEnumId, $leadRecruiterIds, true)) {
                        continue;
                    }

                    // In dev mode, "transferred" is determined by the transfer date field — manager presence is irrelevant
                    if (!$useCustomDateFields && $managerRequired && $managerField !== null && ! $this->fieldHasAnyValue($customFields, (int) $managerField->amo_field_id, $managerField->name, $managerEnumIdsByValue)) {
                        continue;
                    }

                    $cityValues = $cityField
                        ? $this->fieldValueLabels($customFields, (int) $cityField->amo_field_id, $cityField->name, $cityEnumIdsByValue)
                        : [];

                    if ($cityFilter !== '') {
                        $matchesCity = $cityFilter === '—'
                            ? $cityValues === []
                            : in_array($cityFilter, $cityValues, true);
                        if (!$matchesCity) {
                            continue;
                        }
                    }

                    $projectValues = $projectField
                        ? $this->fieldValueLabels($customFields, (int) $projectField->amo_field_id, $projectField->name, $projectEnumIdsByValue)
                        : [];

                    $matchesProject = $projectFilter === 'Без проекта'
                        ? $projectValues === []
                        : ($projectFilter === '' || in_array($projectFilter, $projectValues, true));

                    if (!$matchesProject) {
                        continue;
                    }

                    if ($vacancyFilter !== '') {
                        $vacancyValues = $vacancyField
                            ? $this->fieldValueLabels($customFields, (int) $vacancyField->amo_field_id, $vacancyField->name, $vacancyEnumIdsByValue)
                            : [];

                        $matchesVacancy = $vacancyFilter === '—'
                            ? $vacancyValues === []
                            : in_array($vacancyFilter, $vacancyValues, true);

                        if (!$matchesVacancy) {
                            continue;
                        }
                    }

                    if ($sourceFilter !== '') {
                        $sourceValues = $sourceField
                            ? $this->fieldValueLabels($customFields, (int) $sourceField->amo_field_id, $sourceField->name, $sourceEnumIdsByValue)
                            : [];

                        $matchesSource = $sourceFilter === '—'
                            ? $sourceValues === []
                            : in_array($sourceFilter, $sourceValues, true);

                        if (!$matchesSource) {
                            continue;
                        }
                    }

                    if ($teamFilter !== '') {
                        $teamValues = $teamField
                            ? $this->fieldValueLabels($customFields, (int) $teamField->amo_field_id, $teamField->name, $teamEnumIdsByValue)
                            : [];

                        $matchesTeam = $teamFilter === '—'
                            ? $teamValues === []
                            : in_array($teamFilter, $teamValues, true);

                        if (!$matchesTeam) {
                            continue;
                        }
                    }

                    $total++;
                    if (count($leads) < $limit) {
                        $leads[] = [
                            'id' => $lead->external_id,
                            'name' => $lead->name ?: 'Без названия',
                            'transfer_date' => $transferDate?->toDateString(),
                        ];
                    }
                }
            });

        return [
            'leads' => $leads,
            'total' => $total,
            'limited' => $total > $limit,
            'limit' => $limit,
        ];
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

    private function buildRecruiterScheduleBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $pipelineName = data_get($config, 'pipeline_name');
        $successStatusId = (int) data_get($config, 'success_status_id', 0);
        $successStatusName = (string) (data_get($config, 'success_status_name') ?: 'Встал в график');

        $fieldQuery = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');

        $recruiterField = $this->leadField($fieldQuery, (int) data_get($config, 'recruiter_field_id', 0), (string) (data_get($config, 'recruiter_field_name') ?: self::RECRUITER_FIELD_NAME));
        $managerField = $this->leadField($fieldQuery, (int) data_get($config, 'manager_field_id', 0), (string) (data_get($config, 'manager_field_name') ?: self::MANAGER_FIELD_NAME));

        $recruiterEnumIdsByValue = collect($recruiterField?->enums ?? [])
            ->filter(fn (array $e): bool => isset($e['id']) && isset($e['value']))
            ->mapWithKeys(fn (array $e): array => [$this->normaliseRecruiterValue($e['value']) => (int) $e['id']])
            ->all();

        $managerEnumIdsByValue = collect($managerField?->enums ?? [])
            ->filter(fn (array $e): bool => isset($e['id']) && isset($e['value']))
            ->mapWithKeys(fn (array $e): array => [$this->normaliseRecruiterValue($e['value']) => (int) $e['id']])
            ->all();

        $recruiterNames = collect($recruiterField?->enums ?? [])
            ->filter(fn (array $e): bool => isset($e['id']) && isset($e['value']))
            ->mapWithKeys(fn (array $e): array => [(int) $e['id'] => (string) $e['value']])
            ->all();

        $countsByEnum = [];
        $totalCount = 0;

        $recruiterFieldId = (int) ($recruiterField?->amo_field_id ?? 0);
        $recruiterFieldName = $recruiterField?->name ?? self::RECRUITER_FIELD_NAME;
        $useCustomDateFields = (bool) data_get($config, 'use_custom_date_fields', false);

        if (!$useCustomDateFields) {
            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->when($successStatusId > 0, fn ($q) => $q->where('status_id', $successStatusId))
                ->when($from, fn ($q) => $q->where('entity_created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('entity_created_at', '<=', $to))
                ->orderBy('id')
                ->chunkById(500, function ($leads) use (&$countsByEnum, &$totalCount, $recruiterField, $recruiterFieldId, $recruiterFieldName, $recruiterEnumIdsByValue, $managerField, $managerEnumIdsByValue): void {
                    foreach ($leads as $lead) {
                        $customFields = $lead->custom_fields_values ?? [];
                        $rIds = $this->recruiterEnumIds($customFields, $recruiterFieldId, $recruiterFieldName, $recruiterEnumIdsByValue);
                        if ($rIds === [] || !$this->fieldHasAnyValue($customFields, (int) ($managerField?->amo_field_id ?? 0), self::MANAGER_FIELD_NAME, $managerEnumIdsByValue)) {
                            continue;
                        }
                        $totalCount++;
                        $leadId = (string) $lead->external_id;
                        foreach ($rIds as $enumId) {
                            $countsByEnum[$enumId][$leadId] = true;
                        }
                    }
                });
        } else {
            $transferDateFieldId = (int) data_get($config, 'transfer_date_field_id', self::TRANSFER_DATE_FIELD_ID);
            $fromDate = $from?->toDateString();
            $toDate = $to?->toDateString();

            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'name', 'entity_created_at', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->when($successStatusId > 0, fn ($q) => $q->where('status_id', $successStatusId))
                ->orderBy('id')
                ->chunkById(500, function ($leads) use (&$countsByEnum, &$totalCount, $recruiterField, $recruiterFieldId, $recruiterFieldName, $recruiterEnumIdsByValue, $transferDateFieldId, $fromDate, $toDate, $timezone): void {
                    foreach ($leads as $lead) {
                        $customFields = $lead->custom_fields_values ?? [];
                        $rIds = $this->recruiterEnumIds($customFields, $recruiterFieldId, $recruiterFieldName, $recruiterEnumIdsByValue);
                        if ($rIds === []) {
                            continue;
                        }
                        $transferDate = $this->effectiveDate($customFields, $transferDateFieldId, $lead->entity_created_at, $timezone);
                        if (!$this->dateInPeriod($transferDate, $fromDate, $toDate)) {
                            continue;
                        }
                        $totalCount++;
                        $leadId = (string) $lead->external_id;
                        foreach ($rIds as $enumId) {
                            $countsByEnum[$enumId][$leadId] = true;
                        }
                    }
                });
        }

        $recruiters = [];
        foreach ($countsByEnum as $enumId => $leadIds) {
            $recruiters[] = [
                'enum_id' => $enumId,
                'name' => $recruiterNames[$enumId] ?? "Рекрутер {$enumId}",
                'schedule_count' => count($leadIds),
            ];
        }

        usort($recruiters, fn (array $a, array $b): int => $b['schedule_count'] <=> $a['schedule_count']);

        return [
            'field_name' => $recruiterField?->name ?? self::RECRUITER_FIELD_NAME,
            'field_found' => $recruiterField !== null,
            'success_status_id' => $successStatusId ?: null,
            'success_status_name' => $successStatusName,
            'pipeline_id' => $pipelineId ?: null,
            'pipeline_name' => $pipelineName,
            'total_count' => $totalCount,
            'recruiters' => $recruiters,
        ];
    }

    private function buildRecruiterLeadDistribution(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
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

        $emptyResult = [
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

        if ($field === null) {
            return $emptyResult;
        }

        $useCustomDateFields = (bool) data_get($config, 'use_custom_date_fields', false);
        $intakeLeadIdsByEnum = [];
        $transferLeadIdsByEnum = [];
        $allIntakeLeadIds = [];
        $missingIntakeLeads = [];
        $missingIntakeCount = 0;

        if (!$useCustomDateFields) {
            // Prod: DB-level date filter on entity_created_at, manager required for transfer
            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->when($from, fn ($q) => $q->where('entity_created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('entity_created_at', '<=', $to))
                ->orderBy('id')
                ->chunkById(500, function ($leads) use (&$intakeLeadIdsByEnum, &$transferLeadIdsByEnum, &$allIntakeLeadIds, $field, $fieldName, $enumIdsByValue, $managerField, $managerFieldName, $managerEnumIdsByValue): void {
                    foreach ($leads as $lead) {
                        $customFields = $lead->custom_fields_values ?? [];
                        $leadId = (string) $lead->external_id;
                        $allIntakeLeadIds[$leadId] = true;
                        $hasManager = $managerField !== null && $this->fieldHasAnyValue($customFields, (int) $managerField->amo_field_id, $managerFieldName, $managerEnumIdsByValue);
                        foreach ($this->recruiterEnumIds($customFields, (int) $field->amo_field_id, $fieldName, $enumIdsByValue) as $enumId) {
                            $intakeLeadIdsByEnum[$enumId][$leadId] = true;
                            if ($hasManager) {
                                $transferLeadIdsByEnum[$enumId][$leadId] = true;
                            }
                        }
                    }
                });
        } else {
            $takenToWorkFieldId = (int) data_get($config, 'taken_to_work_field_id', self::TAKEN_TO_WORK_FIELD_ID);
            $transferDateFieldId = (int) data_get($config, 'transfer_date_field_id', self::TRANSFER_DATE_FIELD_ID);
            $fromDate = $from?->toDateString();
            $toDate = $to?->toDateString();

            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'name', 'entity_created_at', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($query) => $query->where('pipeline_id', $pipelineId))
                ->orderBy('id')
                ->chunkById(500, function ($leads) use (&$intakeLeadIdsByEnum, &$transferLeadIdsByEnum, &$allIntakeLeadIds, &$missingIntakeLeads, &$missingIntakeCount, $field, $fieldName, $enumIdsByValue, $takenToWorkFieldId, $transferDateFieldId, $fromDate, $toDate, $timezone): void {
                    foreach ($leads as $lead) {
                        $customFields = $lead->custom_fields_values ?? [];
                        $leadId = (string) $lead->external_id;

                        $recruiterEnumIds = $this->recruiterEnumIds($customFields, (int) $field->amo_field_id, $fieldName, $enumIdsByValue);
                        $hasRecruiter = $recruiterEnumIds !== [];

                        $intakeCustomDate = $this->customDateFieldValue($customFields, $takenToWorkFieldId, $timezone);
                        $createdAtInTz = $lead->entity_created_at?->copy()->setTimezone($timezone);

                        if ($intakeCustomDate === null && $this->dateInPeriod($createdAtInTz, $fromDate, $toDate)) {
                            $missingIntakeCount++;
                            if (count($missingIntakeLeads) < self::MISSING_DATES_LEAD_LIMIT) {
                                $missingIntakeLeads[] = [
                                    'id' => (int) $lead->external_id,
                                    'name' => $lead->name ?: 'Без названия',
                                    'created_at' => $createdAtInTz?->toDateString(),
                                ];
                            }
                        }

                        $intakeDate = $intakeCustomDate ?? $createdAtInTz;
                        $transferDate = $this->effectiveDate($customFields, $transferDateFieldId, $lead->entity_created_at, $timezone);

                    if ($this->dateInPeriod($intakeDate, $fromDate, $toDate)) {
                        $allIntakeLeadIds[$leadId] = true;
                        if ($hasRecruiter) {
                            foreach ($recruiterEnumIds as $enumId) {
                                $intakeLeadIdsByEnum[$enumId][$leadId] = true;
                            }
                        }
                    }

                    if ($this->dateInPeriod($transferDate, $fromDate, $toDate) && $hasRecruiter) {
                        foreach ($recruiterEnumIds as $enumId) {
                            $transferLeadIdsByEnum[$enumId][$leadId] = true;
                        }
                    }
                }
            });
        } // end else (use_custom_date_fields)

        foreach ($intakeLeadIdsByEnum as $enumId => $leadIds) {
            $enums[$enumId] ??= ['enum_id' => $enumId, 'name' => "Значение {$enumId}", 'leads_count' => 0, 'transferred_to_manager_count' => 0];
            $enums[$enumId]['leads_count'] = count($leadIds);
        }
        foreach ($transferLeadIdsByEnum as $enumId => $leadIds) {
            $enums[$enumId] ??= ['enum_id' => $enumId, 'name' => "Значение {$enumId}", 'leads_count' => 0, 'transferred_to_manager_count' => 0];
            $enums[$enumId]['transferred_to_manager_count'] = count($leadIds);
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
            'total_leads_count' => count($allIntakeLeadIds),
            'assigned_leads_count' => collect($intakeLeadIdsByEnum)
                ->flatMap(fn (array $ids): array => array_keys($ids))
                ->unique()
                ->count(),
            'transferred_to_manager_count' => collect($transferLeadIdsByEnum)
                ->flatMap(fn (array $ids): array => array_keys($ids))
                ->unique()
                ->count(),
            'recruiters' => $rows,
            'missing_intake_dates' => [
                'count' => $missingIntakeCount,
                'truncated' => $missingIntakeCount > count($missingIntakeLeads),
                'leads' => $missingIntakeLeads,
            ],
        ];
    }

    private function buildRecruiterTeamCityBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
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
        $managerEnumIdsByValue = $this->enumIdsByValue($managerField);

        if ($recruiterField === null || $teamField === null || $cityField === null) {
            return [
                'pipeline_id' => $pipelineId ?: null,
                'pipeline_name' => $pipelineName,
                'recruiter_field_found' => $recruiterField !== null,
                'manager_field_found' => false,
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
        $withoutTeamCount = 0;

        $useCustomDateFields = (bool) data_get($config, 'use_custom_date_fields', false);

        if (!$useCustomDateFields) {
            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->when($from, fn ($q) => $q->where('entity_created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('entity_created_at', '<=', $to))
                ->orderBy('id')
                ->chunkById(500, function ($leads) use (&$rows, &$totalLeads, &$withoutTeamCount, &$sourceColumns, $recruiterField, $managerField, $teamField, $cityField, $sourceField, $recruiterNames, $recruiterEnumIdsByValue, $managerEnumIdsByValue, $teamEnumIdsByValue, $cityEnumIdsByValue, $sourceEnumIdsByValue): void {
                    foreach ($leads as $lead) {
                        $customFields = $lead->custom_fields_values ?? [];
                        $recruiterIds = $this->recruiterEnumIds($customFields, (int) $recruiterField->amo_field_id, $recruiterField->name, $recruiterEnumIdsByValue);
                        if ($recruiterIds === []) {
                            continue;
                        }
                        if ($managerField !== null && !$this->fieldHasAnyValue($customFields, (int) $managerField->amo_field_id, $managerField->name, $managerEnumIdsByValue)) {
                            continue;
                        }
                        $teamValues = $this->fieldValueLabels($customFields, (int) $teamField->amo_field_id, $teamField->name, $teamEnumIdsByValue);
                        $cityValues = $this->fieldValueLabels($customFields, (int) $cityField->amo_field_id, $cityField->name, $cityEnumIdsByValue);
                        $sourceValues = $sourceField !== null
                            ? $this->fieldValueLabels($customFields, (int) $sourceField->amo_field_id, $sourceField->name, $sourceEnumIdsByValue)
                            : [];
                        $totalLeads++;
                        if ($teamValues === []) { $withoutTeamCount++; continue; }
                        if ($cityValues === []) { continue; }
                        foreach ($recruiterIds as $recruiterId) {
                            $rows[$recruiterId] ??= ['enum_id' => $recruiterId, 'name' => $recruiterNames[$recruiterId] ?? "Значение {$recruiterId}", 'total_leads_count' => 0, 'teams' => []];
                            $rows[$recruiterId]['total_leads_count']++;
                            foreach ($teamValues as $teamValue) {
                                $rows[$recruiterId]['teams'][$teamValue] ??= ['name' => $teamValue, 'total_leads_count' => 0, 'cities' => []];
                                $rows[$recruiterId]['teams'][$teamValue]['total_leads_count']++;
                                foreach ($cityValues as $cityValue) {
                                    $rows[$recruiterId]['teams'][$teamValue]['cities'][$cityValue] ??= ['name' => $cityValue, 'leads_count' => 0, 'sources' => []];
                                    $rows[$recruiterId]['teams'][$teamValue]['cities'][$cityValue]['leads_count']++;
                                    foreach ($sourceValues as $sourceValue) {
                                        if (! in_array($sourceValue, $sourceColumns, true)) { $sourceColumns[] = $sourceValue; }
                                        $rows[$recruiterId]['teams'][$teamValue]['cities'][$cityValue]['sources'][$sourceValue] ??= 0;
                                        $rows[$recruiterId]['teams'][$teamValue]['cities'][$cityValue]['sources'][$sourceValue]++;
                                    }
                                }
                            }
                        }
                    }
                });
        } else {
            $transferDateFieldId = (int) data_get($config, 'transfer_date_field_id', self::TRANSFER_DATE_FIELD_ID);
            $fromDate = $from?->toDateString();
            $toDate = $to?->toDateString();

            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'name', 'entity_created_at', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($query) => $query->where('pipeline_id', $pipelineId))
                ->orderBy('id')
                ->chunkById(500, function ($leads) use (&$rows, &$totalLeads, &$withoutTeamCount, &$sourceColumns, $recruiterField, $teamField, $cityField, $sourceField, $recruiterNames, $recruiterEnumIdsByValue, $teamEnumIdsByValue, $cityEnumIdsByValue, $sourceEnumIdsByValue, $transferDateFieldId, $fromDate, $toDate, $timezone): void {
                    foreach ($leads as $lead) {

                        $customFields = $lead->custom_fields_values ?? [];

                        $recruiterIds = $this->recruiterEnumIds($customFields, (int) $recruiterField->amo_field_id, $recruiterField->name, $recruiterEnumIdsByValue);

                        if ($recruiterIds === []) {
                            continue;
                        }

                        $transferDate = $this->effectiveDate($customFields, $transferDateFieldId, $lead->entity_created_at, $timezone);
                        if (!$this->dateInPeriod($transferDate, $fromDate, $toDate)) {
                            continue;
                        }

                    $teamValues = $this->fieldValueLabels($customFields, (int) $teamField->amo_field_id, $teamField->name, $teamEnumIdsByValue);
                    $cityValues = $this->fieldValueLabels($customFields, (int) $cityField->amo_field_id, $cityField->name, $cityEnumIdsByValue);
                    $sourceValues = $sourceField !== null
                        ? $this->fieldValueLabels($customFields, (int) $sourceField->amo_field_id, $sourceField->name, $sourceEnumIdsByValue)
                        : [];

                    $totalLeads++;

                    if ($teamValues === []) {
                        $withoutTeamCount++;
                        continue;
                    }

                    if ($cityValues === []) {
                        continue;
                    }

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
        } // end else (use_custom_date_fields) for TeamCity

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
            'without_team_count' => $withoutTeamCount,
            'source_columns' => $sourceColumns,
            'recruiters' => $recruiters,
        ];
    }

    private function buildProjectCityVacancyBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $pipelineName = data_get($config, 'pipeline_name');
        $fieldQuery = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');

        $recruiterField = $this->leadField($fieldQuery, (int) data_get($config, 'recruiter_field_id', 0), (string) (data_get($config, 'recruiter_field_name') ?: self::RECRUITER_FIELD_NAME));
        $managerField = $this->leadField($fieldQuery, (int) data_get($config, 'manager_field_id', 0), (string) (data_get($config, 'manager_field_name') ?: self::MANAGER_FIELD_NAME));
        $teamField = $this->leadField($fieldQuery, (int) data_get($config, 'team_field_id', 0), (string) (data_get($config, 'team_field_name') ?: self::TEAM_FIELD_NAME));
        $projectField = $this->leadField($fieldQuery, (int) data_get($config, 'project_field_id', 0), (string) (data_get($config, 'project_field_name') ?: self::PROJECT_FIELD_NAME));
        $cityField = $this->leadField($fieldQuery, (int) data_get($config, 'city_field_id', 0), (string) (data_get($config, 'city_field_name') ?: self::CITY_FIELD_NAME));
        $vacancyField = $this->leadField($fieldQuery, (int) data_get($config, 'vacancy_field_id', 0), (string) (data_get($config, 'vacancy_field_name') ?: self::VACANCY_FIELD_NAME));
        $sourceField = $this->leadField($fieldQuery, (int) data_get($config, 'source_field_id', 0), (string) (data_get($config, 'source_field_name') ?: self::SOURCE_FIELD_NAME));

        $recruiterEnumIdsByValue = $recruiterField ? $this->enumIdsByValue($recruiterField) : [];
        $teamEnumIdsByValue = $teamField ? $this->enumIdsByValue($teamField) : [];
        $projectEnumIdsByValue = $projectField ? $this->enumIdsByValue($projectField) : [];
        $cityEnumIdsByValue = $cityField ? $this->enumIdsByValue($cityField) : [];
        $vacancyEnumIdsByValue = $vacancyField ? $this->enumIdsByValue($vacancyField) : [];
        $sourceEnumIdsByValue = $sourceField ? $this->enumIdsByValue($sourceField) : [];

        $projects = [];
        $allSourceNames = [];
        $totalLeads = 0;
        $useCustomDateFields = (bool) data_get($config, 'use_custom_date_fields', false);

        $chunkCallback = function ($leads) use (&$projects, &$allSourceNames, &$totalLeads, $recruiterField, $projectField, $cityField, $vacancyField, $sourceField, $recruiterEnumIdsByValue, $projectEnumIdsByValue, $cityEnumIdsByValue, $vacancyEnumIdsByValue, $sourceEnumIdsByValue): void {
            foreach ($leads as $lead) {
                $customFields = $lead->custom_fields_values ?? [];
                if ($recruiterField !== null && $this->recruiterEnumIds($customFields, (int) $recruiterField->amo_field_id, $recruiterField->name, $recruiterEnumIdsByValue) === []) {
                    continue;
                }
                $cityValues = $cityField ? $this->fieldValueLabels($customFields, (int) $cityField->amo_field_id, $cityField->name, $cityEnumIdsByValue) : [];
                $projectValues = $projectField ? $this->fieldValueLabels($customFields, (int) $projectField->amo_field_id, $projectField->name, $projectEnumIdsByValue) : [];
                $vacancyValues = $vacancyField ? $this->fieldValueLabels($customFields, (int) $vacancyField->amo_field_id, $vacancyField->name, $vacancyEnumIdsByValue) : [];
                $sourceValues = $sourceField ? $this->fieldValueLabels($customFields, (int) $sourceField->amo_field_id, $sourceField->name, $sourceEnumIdsByValue) : [];
                $projectKeys = $projectValues ?: ['Без проекта'];
                $cityKeys = $cityValues ?: ['—'];
                $vacancyKeys = $vacancyValues ?: ['—'];
                $sourceKeys = $sourceValues ?: ['—'];
                foreach ($sourceKeys as $sourceName) { $allSourceNames[$sourceName] = true; }
                $totalLeads++;
                foreach ($projectKeys as $projectName) {
                    $projects[$projectName] ??= ['name' => $projectName, 'total_leads_count' => 0, 'cities' => []];
                    $projects[$projectName]['total_leads_count']++;
                    foreach ($cityKeys as $cityName) {
                        $projects[$projectName]['cities'][$cityName] ??= ['name' => $cityName, 'leads_count' => 0, 'vacancies' => []];
                        $projects[$projectName]['cities'][$cityName]['leads_count']++;
                        foreach ($vacancyKeys as $vacancyName) {
                            $projects[$projectName]['cities'][$cityName]['vacancies'][$vacancyName] ??= ['leads_count' => 0, 'sources' => []];
                            $projects[$projectName]['cities'][$cityName]['vacancies'][$vacancyName]['leads_count']++;
                            foreach ($sourceKeys as $sourceName) {
                                $projects[$projectName]['cities'][$cityName]['vacancies'][$vacancyName]['sources'][$sourceName] ??= 0;
                                $projects[$projectName]['cities'][$cityName]['vacancies'][$vacancyName]['sources'][$sourceName]++;
                            }
                        }
                    }
                }
            }
        };

        if (!$useCustomDateFields) {
            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->when($from, fn ($q) => $q->where('entity_created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('entity_created_at', '<=', $to))
                ->orderBy('id')
                ->chunkById(500, $chunkCallback);
        } else {
            $transferDateFieldId = (int) data_get($config, 'transfer_date_field_id', self::TRANSFER_DATE_FIELD_ID);
            $fromDate = $from?->toDateString();
            $toDate = $to?->toDateString();

            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'name', 'entity_created_at', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->orderBy('id')
                ->chunkById(500, function ($leads) use ($chunkCallback, $transferDateFieldId, $fromDate, $toDate, $timezone, $recruiterField, $recruiterEnumIdsByValue): void {
                    $filtered = $leads->filter(function ($lead) use ($transferDateFieldId, $fromDate, $toDate, $timezone, $recruiterField, $recruiterEnumIdsByValue): bool {
                        $customFields = $lead->custom_fields_values ?? [];
                        // Recruiter must be set
                        if ($recruiterField !== null && $this->recruiterEnumIds($customFields, (int) $recruiterField->amo_field_id, $recruiterField->name, $recruiterEnumIdsByValue) === []) {
                            return false;
                        }
                        $transferDate = $this->effectiveDate($customFields, $transferDateFieldId, $lead->entity_created_at, $timezone);
                        return $this->dateInPeriod($transferDate, $fromDate, $toDate);
                    });
                    $chunkCallback($filtered);
                });
        }

        $sourceColumns = array_keys($allSourceNames);

        $projectsList = collect($projects)
            ->map(function (array $project): array {
                $project['cities'] = collect($project['cities'])
                    ->map(function (array $city): array {
                        $city['vacancies'] = collect($city['vacancies'])
                            ->map(fn ($data, $name) => ['name' => $name, 'leads_count' => $data['leads_count'], 'sources' => $data['sources']])
                            ->sortByDesc('leads_count')
                            ->values()
                            ->all();

                        return $city;
                    })
                    ->sortByDesc('leads_count')
                    ->values()
                    ->all();

                return $project;
            })
            ->sortByDesc('total_leads_count')
            ->values()
            ->all();

        return [
            'pipeline_id' => $pipelineId ?: null,
            'pipeline_name' => $pipelineName,
            'manager_field_found' => $managerField !== null,
            'manager_field_name' => $managerField?->name ?? self::MANAGER_FIELD_NAME,
            'recruiter_field_found' => $recruiterField !== null,
            'recruiter_field_name' => $recruiterField?->name ?? self::RECRUITER_FIELD_NAME,
            'project_field_found' => $projectField !== null,
            'project_field_name' => $projectField?->name ?? self::PROJECT_FIELD_NAME,
            'city_field_found' => $cityField !== null,
            'city_field_name' => $cityField?->name ?? self::CITY_FIELD_NAME,
            'vacancy_field_found' => $vacancyField !== null,
            'vacancy_field_name' => $vacancyField?->name ?? self::VACANCY_FIELD_NAME,
            'source_field_found' => $sourceField !== null,
            'source_field_name' => $sourceField?->name ?? self::SOURCE_FIELD_NAME,
            'source_columns' => $sourceColumns,
            'total_leads_count' => $totalLeads,
            'projects' => $projectsList,
        ];
    }

    private function projectCityVacancyBreakdownCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone = 'UTC'): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_project_city_vacancy_breakdown',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
            $timezone,
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'project_field_id') ?: data_get($config, 'project_field_name', self::PROJECT_FIELD_NAME),
            data_get($config, 'city_field_id') ?: data_get($config, 'city_field_name', self::CITY_FIELD_NAME),
            data_get($config, 'vacancy_field_id') ?: data_get($config, 'vacancy_field_name', self::VACANCY_FIELD_NAME),
            data_get($config, 'source_field_id') ?: data_get($config, 'source_field_name', self::SOURCE_FIELD_NAME),
            data_get($config, 'use_custom_date_fields') ? 'dev' : 'prod',
        ]);
    }

    private function recruiterScheduleBreakdownCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone = 'UTC'): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_recruiter_schedule_breakdown',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
            $timezone,
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'success_status_id') ?: 'none',
            data_get($config, 'recruiter_field_id') ?: data_get($config, 'recruiter_field_name', self::RECRUITER_FIELD_NAME),
            data_get($config, 'use_custom_date_fields') ? 'dev' : 'prod',
        ]);
    }

    private function recruiterLeadDistributionCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone = 'UTC'): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_recruiter_lead_distribution',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
            $timezone,
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'recruiter_field_id') ?: data_get($config, 'recruiter_field_name', self::RECRUITER_FIELD_NAME),
            data_get($config, 'manager_field_id') ?: data_get($config, 'manager_field_name', self::MANAGER_FIELD_NAME),
            data_get($config, 'use_custom_date_fields') ? 'dev' : 'prod',
        ]);
    }

    private function recruiterTeamCityBreakdownCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone = 'UTC'): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_recruiter_team_city_breakdown',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
            $timezone,
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'recruiter_field_id') ?: data_get($config, 'recruiter_field_name', self::RECRUITER_FIELD_NAME),
            data_get($config, 'team_field_id') ?: data_get($config, 'team_field_name', self::TEAM_FIELD_NAME),
            data_get($config, 'city_field_id') ?: data_get($config, 'city_field_name', self::CITY_FIELD_NAME),
            data_get($config, 'source_field_id') ?: data_get($config, 'source_field_name', self::SOURCE_FIELD_NAME),
            data_get($config, 'use_custom_date_fields') ? 'dev' : 'prod',
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

    // Parse a date custom field (stored as Unix timestamp = midnight in account timezone).
    private function customDateFieldValue(array $customFields, int $fieldId, string $timezone): ?Carbon
    {
        foreach ($customFields as $field) {
            $fId = (int) ($field['field_id'] ?? $field['id'] ?? 0);
            if ($fId !== $fieldId) {
                continue;
            }
            $value = $field['values'][0]['value'] ?? null;
            if ($value === null || $value === '') {
                return null;
            }
            return is_numeric($value)
                ? Carbon::createFromTimestamp((int) $value, $timezone)
                : Carbon::parse((string) $value, $timezone);
        }
        return null;
    }

    // Y-m-d string comparison to avoid DST edge cases.
    private function dateInPeriod(?Carbon $date, ?string $fromDate, ?string $toDate): bool
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

    private function effectiveDate(array $customFields, int $fieldId, ?Carbon $entityCreatedAt, string $timezone): ?Carbon
    {
        $fieldDate = $this->customDateFieldValue($customFields, $fieldId, $timezone);
        if ($fieldDate !== null) {
            return $fieldDate;
        }
        return $entityCreatedAt?->copy()->setTimezone($timezone);
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

                    $completeTill = $this->timestamp($raw['complete_till'] ?? null);
                    $completedAt = $this->completionTime($raw) ?? $completeTill;

                    if (! $this->inPeriod($task->entity_created_at, $from, $to)) {
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

        // amoCRM displays task dates in UTC, so compare UTC date strings
        // to match what amoCRM shows in its filters.
        $dateStr = $date->utc()->toDateString();

        if ($from !== null && $dateStr < $from->toDateString()) {
            return false;
        }

        if ($to !== null && $dateStr > $to->toDateString()) {
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
        return data_get($user->raw, '_embedded.groups.0.name')
            ?: data_get($user->raw, '_embedded.group.name')
            ?: data_get($user->raw, 'group.name')
            ?: ($user->group_id ? "Группа {$user->group_id}" : 'Отдел продаж');
    }

}
