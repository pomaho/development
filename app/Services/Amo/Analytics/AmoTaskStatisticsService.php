<?php

namespace App\Services\Amo\Analytics;

use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Models\CrmCustomFieldSnapshot;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineStatusSnapshot;
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
    private const DEFAULT_LEADS_PLAN_PER_DAY = 8.5;
    private const DEFAULT_MANAGER_PLAN_PERCENT = 25.0;
    private const AVITO_CABINET_TAGS = ['Берем Всех', 'СуперПрофи', 'ПартнерСервис', 'Твой Доход', 'Твоя Работа'];
    private const SHIFT_DATE_FIELD_NAME = 'Дата смены';


    /**
     * Hardcoded РП → команда hierarchy for account_id=2 (anyservice), keyed by amo_user_id.
     * amoCRM's own "group" field does not reliably reflect real team leadership (several
     * distinct РП share one amoCRM group), so this mapping was compiled manually from the
     * list provided by the client on 2026-07-23 and matched against amo_users_snapshots.
     */
    private function managerHierarchy(AmoAccount $account): array
    {
        if ($account->id !== 2) {
            return [];
        }

        return [
            12981002 => ['label' => 'Жукова Гаян', 'members' => [13848434, 13886858, 12950366, 13073270, 12982050]],
            13004058 => ['label' => 'Александр Ли', 'members' => [13115030, 13001706, 13001902]],
            12950278 => ['label' => 'Маджидов Тохир', 'members' => [12979762, 14056542, 11721374]],
            11781154 => ['label' => 'Слуцкий Даниил', 'members' => [11762974, 12980990, 13248546]],
            14056554 => ['label' => 'Беканов Азиз', 'members' => [14056558, 14056566, 14056574, 14056582]],
        ];
    }

    public function statistics(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return Cache::remember(
            $this->statisticsCacheKey($account, $from, $to),
            now()->addMinutes(10),
            fn (): array => $this->buildStatistics($account, $from, $to),
        );
    }

    private function buildStatistics(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
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
            ->forceIndex('ces_account_type_id')
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

        $hierarchy = $this->managerHierarchy($account);
        $memberToRp = [];

        foreach ($hierarchy as $rpId => $team) {
            foreach ($team['members'] as $memberId) {
                $memberToRp[$memberId] = $rpId;
            }
        }

        $groups = [];

        foreach ($userRows as $responsibleId => $row) {
            if (isset($hierarchy[$responsibleId])) {
                // РП сам не выводится отдельной строкой — только агрегат по его команде.
                continue;
            }

            if (isset($memberToRp[$responsibleId])) {
                $rpId = $memberToRp[$responsibleId];
                $groupKey = 'rp_'.$rpId;
                $groups[$groupKey] ??= [
                    'group_id' => $rpId,
                    'group_name' => $hierarchy[$rpId]['label'].' — РП',
                    'is_rp_team' => true,
                    'users' => [],
                ];
                $groups[$groupKey]['users'][] = $row;
                continue;
            }

            $user = $users->get($responsibleId);
            $groupId = $user?->group_id ? (int) $user->group_id : 0;
            $groups[$groupId] ??= [
                'group_id' => $groupId ?: null,
                'group_name' => $user ? $this->groupName($user) : 'Без группы',
                'is_rp_team' => false,
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
                $group['overdue_rate'] = $group['completed_count'] > 0
                    ? round($group['completed_overdue_count'] / $group['completed_count'] * 100, 1)
                    : 0.0;

                return $group;
            })
            ->sortBy(fn (array $group): string => ($group['is_rp_team'] ? '0_' : '1_').$group['group_name'])
            ->values()
            ->all();
    }

    public function userOverdueTasks(AmoAccount $account, int $userId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return Cache::remember(
            $this->userOverdueTasksCacheKey($account, $userId, $from, $to),
            now()->addMinutes(10),
            fn (): array => $this->buildUserOverdueTasks($account, $userId, $from, $to),
        );
    }

    private function buildUserOverdueTasks(AmoAccount $account, int $userId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $tasks = [];

        CrmEntitySnapshot::query()
            ->select(['id', 'entity_created_at', 'raw', 'name', 'embedded'])
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

                    $embedded = $task->embedded ?? [];
                    $leadId = ($embedded['entity_type'] ?? null) === 'leads'
                        ? (int) ($embedded['entity_id'] ?? 0) ?: null
                        : null;

                    $tasks[] = [
                        'text' => $raw['text'] ?? $task->name,
                        'complete_till' => $completeTill->format('d.m.Y'),
                        'completed_at' => $completedAt->format('d.m.Y'),
                        'days_overdue' => (int) $completedAt->diffInDays($completeTill),
                        'lead_id' => $leadId,
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

    public function managerLeadDistribution(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        return Cache::remember(
            $this->managerLeadDistributionCacheKey($account, $from, $to, $config, $timezone),
            now()->addMinutes(10),
            fn (): array => $this->buildManagerLeadDistribution($account, $from, $to, $config, $timezone),
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

    public function avitoCabinetBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return Cache::remember(
            $this->avitoCabinetBreakdownCacheKey($account, $from, $to),
            now()->addMinutes(10),
            fn (): array => $this->buildAvitoCabinetBreakdown($account, $from, $to),
        );
    }

    public function shiftDateLeads(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        return Cache::remember(
            $this->shiftDateLeadsCacheKey($account, $from, $to, $config, $timezone),
            now()->addMinutes(10),
            fn (): array => $this->buildShiftDateLeads($account, $from, $to, $config, $timezone),
        );
    }

    public function avitoCabinetLeads(AmoAccount $account, ?Carbon $from, ?Carbon $to, string $cabinetName, bool $successOnly = false, int $limit = 200): array
    {
        $successPairs = CrmPipelineStatusSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->whereRaw('LOWER(name) LIKE ?', ['%встал в график%'])
            ->get(['amo_pipeline_id', 'amo_status_id'])
            ->map(fn ($status): string => "{$status->amo_pipeline_id}:{$status->amo_status_id}")
            ->flip()
            ->all();

        $normalisedCabinet = mb_strtolower(trim($cabinetName));
        $leads = [];
        $total = 0;

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'pipeline_id', 'status_id', 'entity_created_at', 'embedded'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($from, fn ($q) => $q->where('entity_created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('entity_created_at', '<=', $to))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$leads, &$total, $limit, $normalisedCabinet, $successOnly, $successPairs): void {
                foreach ($chunk as $lead) {
                    $tags = collect($lead->embedded['tags'] ?? [])->pluck('name');
                    $matchesCabinet = $tags->contains(fn ($tagName): bool => mb_strtolower(trim((string) $tagName)) === $normalisedCabinet);
                    if (!$matchesCabinet) {
                        continue;
                    }

                    if ($successOnly && !isset($successPairs["{$lead->pipeline_id}:{$lead->status_id}"])) {
                        continue;
                    }

                    $total++;
                    if (count($leads) < $limit) {
                        $leads[] = [
                            'id' => $lead->external_id,
                            'name' => $lead->name ?: 'Без названия',
                            'created_at' => $lead->entity_created_at?->toDateString(),
                        ];
                    }
                }
            });

        return ['leads' => $leads, 'total' => $total, 'limited' => $total > $limit, 'limit' => $limit];
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
                    $transferDate = null;

                    if ($useCustomDateFields) {
                        $managerFieldId = (int) ($managerField?->amo_field_id ?? 0);
                        $transferDate = $this->transferEffectiveDate($customFields, $transferDateFieldId, $managerFieldId, self::MANAGER_FIELD_NAME, $managerEnumIdsByValue, $lead->entity_created_at, $timezone);
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
                            'created_at' => $lead->entity_created_at?->toDateString(),
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

    /**
     * Lead list for the manager-leads report drill-down popup. $managerEnumId = 0 means
     * the "Менеджер не указан" bucket (transferred per the transfer-date field, but the
     * "Менеджер" field is currently empty) — mirrors the cohort logic in
     * buildManagerLeadDistribution() exactly, so the counts always match.
     */
    public function managerLeads(
        AmoAccount $account,
        ?Carbon $from,
        ?Carbon $to,
        array $config,
        int $managerEnumId,
        bool $scheduledOnly = false,
        int $limit = 200,
        string $timezone = 'UTC'
    ): array {
        $managerFieldId = (int) data_get($config, 'manager_field_id', 0);
        $managerFieldName = (string) (data_get($config, 'manager_field_name') ?: self::MANAGER_FIELD_NAME);
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $successStatusId = (int) data_get($config, 'success_status_id', 0);

        $fieldQuery = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');

        $managerField = $managerFieldId > 0
            ? (clone $fieldQuery)->where('amo_field_id', $managerFieldId)->first()
            : (clone $fieldQuery)->where('name', $managerFieldName)->first();

        if ($managerField === null) {
            return ['leads' => [], 'total' => 0, 'limited' => false, 'limit' => $limit];
        }

        $managerEnumIdsByValue = $this->enumIdsByValue($managerField);
        $managerFieldIdInt = (int) $managerField->amo_field_id;

        $leads = [];
        $total = 0;
        $useCustomDateFields = (bool) data_get($config, 'use_custom_date_fields', false);

        if (!$useCustomDateFields) {
            // The "Менеджер не указан" bucket only exists in dev mode (it relies on the
            // transfer-date field being independent from the manager field); in prod mode
            // a lead is only ever "transferred" when the manager field itself is filled.
            if ($managerEnumId === 0) {
                return ['leads' => [], 'total' => 0, 'limited' => false, 'limit' => $limit];
            }

            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'name', 'status_id', 'entity_created_at', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->when($from, fn ($q) => $q->where('entity_created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('entity_created_at', '<=', $to))
                ->orderBy('id')
                ->chunkById(500, function ($chunk) use (&$leads, &$total, $limit, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue, $managerEnumId, $scheduledOnly, $successStatusId): void {
                    foreach ($chunk as $lead) {
                        $customFields = $lead->custom_fields_values ?? [];
                        $managerEnumIds = $this->recruiterEnumIds($customFields, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue);

                        if (!in_array($managerEnumId, $managerEnumIds, true)) {
                            continue;
                        }

                        if ($scheduledOnly && (int) $lead->status_id !== $successStatusId) {
                            continue;
                        }

                        $total++;
                        if (count($leads) < $limit) {
                            $leads[] = [
                                'id' => $lead->external_id,
                                'name' => $lead->name ?: 'Без названия',
                                'created_at' => $lead->entity_created_at?->toDateString(),
                            ];
                        }
                    }
                });

            return ['leads' => $leads, 'total' => $total, 'limited' => $total > $limit, 'limit' => $limit];
        }

        $transferDateFieldId = (int) data_get($config, 'transfer_date_field_id', self::TRANSFER_DATE_FIELD_ID);
        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'status_id', 'entity_created_at', 'custom_fields_values'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$leads, &$total, $limit, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue, $transferDateFieldId, $fromDate, $toDate, $timezone, $managerEnumId, $scheduledOnly, $successStatusId): void {
                foreach ($chunk as $lead) {
                    $customFields = $lead->custom_fields_values ?? [];
                    $managerEnumIds = $this->recruiterEnumIds($customFields, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue);

                    $transferDate = $this->transferEffectiveDate($customFields, $transferDateFieldId, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue, $lead->entity_created_at, $timezone);

                    if (!$this->dateInPeriod($transferDate, $fromDate, $toDate)) {
                        continue;
                    }

                    $matches = $managerEnumId === 0
                        ? $managerEnumIds === []
                        : in_array($managerEnumId, $managerEnumIds, true);

                    if (!$matches) {
                        continue;
                    }

                    if ($scheduledOnly && (int) $lead->status_id !== $successStatusId) {
                        continue;
                    }

                    $total++;
                    if (count($leads) < $limit) {
                        $leads[] = [
                            'id' => $lead->external_id,
                            'name' => $lead->name ?: 'Без названия',
                            'created_at' => $lead->entity_created_at?->toDateString(),
                        ];
                    }
                }
            });

        return ['leads' => $leads, 'total' => $total, 'limited' => $total > $limit, 'limit' => $limit];
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
                ->chunkById(500, function ($leads) use (&$countsByEnum, &$totalCount, $recruiterField, $recruiterFieldId, $recruiterFieldName, $recruiterEnumIdsByValue, $managerField, $managerEnumIdsByValue, $transferDateFieldId, $fromDate, $toDate, $timezone): void {
                    foreach ($leads as $lead) {
                        $customFields = $lead->custom_fields_values ?? [];
                        $rIds = $this->recruiterEnumIds($customFields, $recruiterFieldId, $recruiterFieldName, $recruiterEnumIdsByValue);
                        if ($rIds === []) {
                            continue;
                        }
                        $managerFieldId = (int) ($managerField?->amo_field_id ?? 0);
                        $transferDate = $this->transferEffectiveDate($customFields, $transferDateFieldId, $managerFieldId, self::MANAGER_FIELD_NAME, $managerEnumIdsByValue, $lead->entity_created_at, $timezone);
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

    private function buildShiftDateLeads(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        $fieldQuery = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');

        $shiftDateField = $this->leadField($fieldQuery, (int) data_get($config, 'shift_date_field_id', 0), (string) (data_get($config, 'shift_date_field_name') ?: self::SHIFT_DATE_FIELD_NAME));

        if ($shiftDateField === null) {
            return ['field_found' => false, 'field_name' => self::SHIFT_DATE_FIELD_NAME, 'leads' => []];
        }

        $cityField = $this->leadField($fieldQuery, 0, self::CITY_FIELD_NAME);
        $teamField = $this->leadField($fieldQuery, 0, self::TEAM_FIELD_NAME);
        $managerField = $this->leadField($fieldQuery, 0, self::MANAGER_FIELD_NAME);
        $recruiterField = $this->leadField($fieldQuery, 0, self::RECRUITER_FIELD_NAME);

        $shiftDateFieldId = (int) $shiftDateField->amo_field_id;
        $cityFieldId = (int) ($cityField?->amo_field_id ?? 0);
        $teamFieldId = (int) ($teamField?->amo_field_id ?? 0);
        $managerFieldId = (int) ($managerField?->amo_field_id ?? 0);
        $recruiterFieldId = (int) ($recruiterField?->amo_field_id ?? 0);

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();
        $leads = [];

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'custom_fields_values'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$leads, $shiftDateFieldId, $cityFieldId, $teamFieldId, $managerFieldId, $recruiterFieldId, $fromDate, $toDate, $timezone): void {
                foreach ($chunk as $lead) {
                    $customFields = $lead->custom_fields_values ?? [];
                    $shiftDate = $this->customDateFieldValue($customFields, $shiftDateFieldId, $timezone);

                    if ($shiftDate === null || !$this->dateInPeriod($shiftDate, $fromDate, $toDate)) {
                        continue;
                    }

                    $leads[] = [
                        'id' => $lead->external_id,
                        'name' => $lead->name ?: 'Без названия',
                        'shift_date' => $shiftDate->toDateString(),
                        'city' => implode(', ', $this->fieldValueLabels($customFields, $cityFieldId, self::CITY_FIELD_NAME, [])) ?: null,
                        'team' => implode(', ', $this->fieldValueLabels($customFields, $teamFieldId, self::TEAM_FIELD_NAME, [])) ?: null,
                        'manager' => implode(', ', $this->fieldValueLabels($customFields, $managerFieldId, self::MANAGER_FIELD_NAME, [])) ?: null,
                        'recruiter' => implode(', ', $this->fieldValueLabels($customFields, $recruiterFieldId, self::RECRUITER_FIELD_NAME, [])) ?: null,
                    ];
                }
            });

        usort($leads, fn (array $a, array $b): int => $b['shift_date'] <=> $a['shift_date']);

        return [
            'field_found' => true,
            'field_name' => $shiftDateField->name,
            'leads' => $leads,
        ];
    }

    private function buildAvitoCabinetBreakdown(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $successPairs = CrmPipelineStatusSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->whereRaw('LOWER(name) LIKE ?', ['%встал в график%'])
            ->get(['amo_pipeline_id', 'amo_status_id'])
            ->map(fn ($status): string => "{$status->amo_pipeline_id}:{$status->amo_status_id}")
            ->flip()
            ->all();

        $counts = [];
        foreach (self::AVITO_CABINET_TAGS as $cabinetName) {
            $counts[$cabinetName] = ['total' => 0, 'success' => 0];
        }
        $normalisedCabinets = collect(self::AVITO_CABINET_TAGS)
            ->mapWithKeys(fn (string $name): array => [mb_strtolower(trim($name)) => $name])
            ->all();

        CrmEntitySnapshot::query()
            ->select(['id', 'pipeline_id', 'status_id', 'entity_created_at', 'embedded'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($from, fn ($q) => $q->where('entity_created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('entity_created_at', '<=', $to))
            ->orderBy('id')
            ->chunkById(500, function ($leads) use (&$counts, $normalisedCabinets, $successPairs): void {
                foreach ($leads as $lead) {
                    $tags = collect($lead->embedded['tags'] ?? [])->pluck('name');
                    $isSuccess = isset($successPairs["{$lead->pipeline_id}:{$lead->status_id}"]);

                    foreach ($tags as $tagName) {
                        $cabinetName = $normalisedCabinets[mb_strtolower(trim((string) $tagName))] ?? null;
                        if ($cabinetName === null) {
                            continue;
                        }
                        $counts[$cabinetName]['total']++;
                        if ($isSuccess) {
                            $counts[$cabinetName]['success']++;
                        }
                    }
                }
            });

        $cabinets = [];
        foreach (self::AVITO_CABINET_TAGS as $cabinetName) {
            $cabinets[] = [
                'name' => $cabinetName,
                'total_count' => $counts[$cabinetName]['total'],
                'success_count' => $counts[$cabinetName]['success'],
            ];
        }

        return ['cabinets' => $cabinets];
    }

    private function buildRecruiterLeadDistribution(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        $fieldId = (int) data_get($config, 'recruiter_field_id', 0);
        $fieldName = (string) (data_get($config, 'recruiter_field_name') ?: self::RECRUITER_FIELD_NAME);
        $managerFieldId = (int) data_get($config, 'manager_field_id', 0);
        $managerFieldName = (string) (data_get($config, 'manager_field_name') ?: self::MANAGER_FIELD_NAME);
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $pipelineName = data_get($config, 'pipeline_name');
        $leadsPlanPerDay = (float) data_get($config, 'leads_plan_per_day', self::DEFAULT_LEADS_PLAN_PER_DAY);
        // diffInDays() on Carbon 3 returns a precise float (e.g. 21.999999999988 for a
        // period ending at 23:59:59), so compare calendar-day boundaries to get a clean int.
        $daysInPeriod = ($from !== null && $to !== null)
            ? (int) round($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay())) + 1
            : null;
        $planTotal = $daysInPeriod !== null ? round($daysInPeriod * $leadsPlanPerDay, 2) : null;
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
            'days_in_period' => $daysInPeriod,
            'leads_plan_per_day' => $leadsPlanPerDay,
            'plan_total' => $planTotal,
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
                ->chunkById(500, function ($leads) use (&$intakeLeadIdsByEnum, &$transferLeadIdsByEnum, &$allIntakeLeadIds, &$missingIntakeLeads, &$missingIntakeCount, $field, $fieldName, $enumIdsByValue, $managerField, $managerFieldName, $managerEnumIdsByValue, $takenToWorkFieldId, $transferDateFieldId, $fromDate, $toDate, $timezone): void {
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
                        $managerFieldId = (int) ($managerField?->amo_field_id ?? 0);
                        $transferDate = $this->transferEffectiveDate($customFields, $transferDateFieldId, $managerFieldId, $managerFieldName, $managerEnumIdsByValue, $lead->entity_created_at, $timezone);

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

        foreach ($enums as $enumId => $enum) {
            $enums[$enumId]['plan_total'] = $planTotal;
            $enums[$enumId]['plan_completion_percent'] = ($planTotal !== null && $planTotal > 0)
                ? round($enum['leads_count'] / $planTotal * 100, 1)
                : null;
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
            'days_in_period' => $daysInPeriod,
            'leads_plan_per_day' => $leadsPlanPerDay,
            'plan_total' => $planTotal,
            'recruiters' => $rows,
            'missing_intake_dates' => [
                'count' => $missingIntakeCount,
                'truncated' => $missingIntakeCount > count($missingIntakeLeads),
                'leads' => $missingIntakeLeads,
            ],
        ];
    }

    private function buildManagerLeadDistribution(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null, array $config = [], string $timezone = 'UTC'): array
    {
        $managerFieldId = (int) data_get($config, 'manager_field_id', 0);
        $managerFieldName = (string) (data_get($config, 'manager_field_name') ?: self::MANAGER_FIELD_NAME);
        $pipelineId = (int) data_get($config, 'pipeline_id', 0);
        $pipelineName = data_get($config, 'pipeline_name');
        $successStatusId = (int) data_get($config, 'success_status_id', 0);
        $successStatusName = (string) (data_get($config, 'success_status_name') ?: 'Встал в график');
        $managerPlanPercent = (float) data_get($config, 'manager_plan_percent', self::DEFAULT_MANAGER_PLAN_PERCENT);

        $fieldQuery = CrmCustomFieldSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads');

        $managerField = $managerFieldId > 0
            ? (clone $fieldQuery)->where('amo_field_id', $managerFieldId)->first()
            : (clone $fieldQuery)->where('name', $managerFieldName)->first();

        $emptyResult = [
            'manager_field_name' => $managerFieldName,
            'manager_field_id' => null,
            'manager_field_found' => false,
            'success_status_id' => $successStatusId ?: null,
            'success_status_name' => $successStatusName,
            'pipeline_id' => $pipelineId ?: null,
            'pipeline_name' => $pipelineName,
            'manager_plan_percent' => $managerPlanPercent,
            'total_received_count' => 0,
            'total_scheduled_count' => 0,
            'managers' => [],
        ];

        if ($managerField === null) {
            return $emptyResult;
        }

        $managerEnumIdsByValue = $this->enumIdsByValue($managerField);
        $managerNames = $this->enumNamesById($managerField);
        $managerFieldIdInt = (int) $managerField->amo_field_id;

        $receivedLeadIdsByEnum = [];
        $scheduledLeadIdsByEnum = [];
        // Leads whose "Дата передачи менеджеру" is filled (so they were transferred),
        // but the "Менеджер" field is currently empty — e.g. the manager was later
        // unassigned. Tracked separately as an explicit "Менеджер не указан" row instead
        // of being silently dropped, so the total matches the recruiter report's
        // "передано менеджеру" count.
        $unassignedLeadIds = [];
        $unassignedScheduledLeadIds = [];

        $useCustomDateFields = (bool) data_get($config, 'use_custom_date_fields', false);

        if (!$useCustomDateFields) {
            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'status_id', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->when($from, fn ($q) => $q->where('entity_created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('entity_created_at', '<=', $to))
                ->orderBy('id')
                ->chunkById(500, function ($leads) use (&$receivedLeadIdsByEnum, &$scheduledLeadIdsByEnum, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue, $successStatusId): void {
                    foreach ($leads as $lead) {
                        $customFields = $lead->custom_fields_values ?? [];
                        $leadId = (string) $lead->external_id;
                        $managerEnumIds = $this->recruiterEnumIds($customFields, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue);

                        if ($managerEnumIds === []) {
                            continue;
                        }

                        foreach ($managerEnumIds as $enumId) {
                            $receivedLeadIdsByEnum[$enumId][$leadId] = true;

                            if ($successStatusId > 0 && (int) $lead->status_id === $successStatusId) {
                                $scheduledLeadIdsByEnum[$enumId][$leadId] = true;
                            }
                        }
                    }
                });
        } else {
            $transferDateFieldId = (int) data_get($config, 'transfer_date_field_id', self::TRANSFER_DATE_FIELD_ID);
            $fromDate = $from?->toDateString();
            $toDate = $to?->toDateString();

            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'status_id', 'entity_created_at', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->orderBy('id')
                ->chunkById(500, function ($leads) use (&$receivedLeadIdsByEnum, &$scheduledLeadIdsByEnum, &$unassignedLeadIds, &$unassignedScheduledLeadIds, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue, $transferDateFieldId, $fromDate, $toDate, $timezone, $successStatusId): void {
                    foreach ($leads as $lead) {
                        $customFields = $lead->custom_fields_values ?? [];
                        $leadId = (string) $lead->external_id;
                        $managerEnumIds = $this->recruiterEnumIds($customFields, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue);

                        $transferDate = $this->transferEffectiveDate($customFields, $transferDateFieldId, $managerFieldIdInt, $managerFieldName, $managerEnumIdsByValue, $lead->entity_created_at, $timezone);

                        if (!$this->dateInPeriod($transferDate, $fromDate, $toDate)) {
                            continue;
                        }

                        $isScheduled = $successStatusId > 0 && (int) $lead->status_id === $successStatusId;

                        if ($managerEnumIds === []) {
                            $unassignedLeadIds[$leadId] = true;
                            if ($isScheduled) {
                                $unassignedScheduledLeadIds[$leadId] = true;
                            }
                            continue;
                        }

                        foreach ($managerEnumIds as $enumId) {
                            $receivedLeadIdsByEnum[$enumId][$leadId] = true;

                            if ($isScheduled) {
                                $scheduledLeadIdsByEnum[$enumId][$leadId] = true;
                            }
                        }
                    }
                });
        }

        // Managers with zero deals received during the period are intentionally omitted from the report.
        $managers = [];

        foreach ($receivedLeadIdsByEnum as $enumId => $leadIds) {
            $receivedCount = count($leadIds);
            $scheduledCount = count($scheduledLeadIdsByEnum[$enumId] ?? []);
            $planTotal = round($receivedCount * $managerPlanPercent / 100, 2);

            $managers[$enumId] = [
                'enum_id' => $enumId,
                'name' => $managerNames[$enumId] ?? "Менеджер {$enumId}",
                'received_count' => $receivedCount,
                'scheduled_count' => $scheduledCount,
                'plan_total' => $planTotal,
                'plan_completion_percent' => $planTotal > 0 ? round($scheduledCount / $planTotal * 100, 1) : null,
            ];
        }

        $rows = collect($managers)
            ->sortByDesc('received_count')
            ->values()
            ->all();

        if ($unassignedLeadIds !== []) {
            // No accountable person for this bucket, so it carries no plan expectation.
            $rows[] = [
                'enum_id' => 0,
                'name' => 'Менеджер не указан',
                'received_count' => count($unassignedLeadIds),
                'scheduled_count' => count($unassignedScheduledLeadIds),
                'plan_total' => null,
                'plan_completion_percent' => null,
            ];
        }

        return [
            'manager_field_name' => $managerField->name,
            'manager_field_id' => $managerFieldIdInt,
            'manager_field_found' => true,
            'success_status_id' => $successStatusId ?: null,
            'success_status_name' => $successStatusName,
            'pipeline_id' => $pipelineId ?: null,
            'pipeline_name' => $pipelineName,
            'manager_plan_percent' => $managerPlanPercent,
            'total_received_count' => collect($receivedLeadIdsByEnum)
                ->flatMap(fn (array $ids): array => array_keys($ids))
                ->merge(array_keys($unassignedLeadIds))
                ->unique()
                ->count(),
            'total_scheduled_count' => collect($scheduledLeadIdsByEnum)
                ->flatMap(fn (array $ids): array => array_keys($ids))
                ->merge(array_keys($unassignedScheduledLeadIds))
                ->unique()
                ->count(),
            'managers' => $rows,
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
                ->chunkById(500, function ($leads) use (&$rows, &$totalLeads, &$withoutTeamCount, &$sourceColumns, $recruiterField, $managerField, $managerEnumIdsByValue, $teamField, $cityField, $sourceField, $recruiterNames, $recruiterEnumIdsByValue, $teamEnumIdsByValue, $cityEnumIdsByValue, $sourceEnumIdsByValue, $transferDateFieldId, $fromDate, $toDate, $timezone): void {
                    foreach ($leads as $lead) {

                        $customFields = $lead->custom_fields_values ?? [];

                        $recruiterIds = $this->recruiterEnumIds($customFields, (int) $recruiterField->amo_field_id, $recruiterField->name, $recruiterEnumIdsByValue);

                        if ($recruiterIds === []) {
                            continue;
                        }

                        $managerFieldId = (int) ($managerField?->amo_field_id ?? 0);
                        $transferDate = $this->transferEffectiveDate($customFields, $transferDateFieldId, $managerFieldId, self::MANAGER_FIELD_NAME, $managerEnumIdsByValue, $lead->entity_created_at, $timezone);
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
        $managerEnumIdsByValue = $managerField ? $this->enumIdsByValue($managerField) : [];
        $teamEnumIdsByValue = $teamField ? $this->enumIdsByValue($teamField) : [];
        $projectEnumIdsByValue = $projectField ? $this->enumIdsByValue($projectField) : [];
        $cityEnumIdsByValue = $cityField ? $this->enumIdsByValue($cityField) : [];
        $vacancyEnumIdsByValue = $vacancyField ? $this->enumIdsByValue($vacancyField) : [];
        $sourceEnumIdsByValue = $sourceField ? $this->enumIdsByValue($sourceField) : [];

        $allSourceNames = [];
        $totalLeads = 0;
        $useCustomDateFields = (bool) data_get($config, 'use_custom_date_fields', false);

        $commonReturn = [
            'pipeline_id' => $pipelineId ?: null,
            'pipeline_name' => $pipelineName,
            'manager_field_found' => $managerField !== null,
            'manager_field_name' => $managerField?->name ?? self::MANAGER_FIELD_NAME,
            'recruiter_field_found' => $recruiterField !== null,
            'recruiter_field_name' => $recruiterField?->name ?? self::RECRUITER_FIELD_NAME,
            'team_field_found' => $teamField !== null,
            'team_field_name' => $teamField?->name ?? self::TEAM_FIELD_NAME,
            'project_field_found' => $projectField !== null,
            'project_field_name' => $projectField?->name ?? self::PROJECT_FIELD_NAME,
            'city_field_found' => $cityField !== null,
            'city_field_name' => $cityField?->name ?? self::CITY_FIELD_NAME,
            'vacancy_field_found' => $vacancyField !== null,
            'vacancy_field_name' => $vacancyField?->name ?? self::VACANCY_FIELD_NAME,
            'source_field_found' => $sourceField !== null,
            'source_field_name' => $sourceField?->name ?? self::SOURCE_FIELD_NAME,
        ];

        if (!$useCustomDateFields) {
            // PROD: group by project → city → vacancy
            $projects = [];

            $prodCallback = function ($leads) use (&$projects, &$allSourceNames, &$totalLeads, $recruiterField, $projectField, $cityField, $vacancyField, $sourceField, $recruiterEnumIdsByValue, $projectEnumIdsByValue, $cityEnumIdsByValue, $vacancyEnumIdsByValue, $sourceEnumIdsByValue): void {
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

            CrmEntitySnapshot::query()
                ->select(['id', 'external_id', 'custom_fields_values'])
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
                ->when($from, fn ($q) => $q->where('entity_created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('entity_created_at', '<=', $to))
                ->orderBy('id')
                ->chunkById(500, $prodCallback);

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

            return array_merge($commonReturn, [
                'source_columns' => $sourceColumns,
                'total_leads_count' => $totalLeads,
                'projects' => $projectsList,
                'teams' => [],
            ]);
        }

        // DEV: group by team → project → city → vacancy
        $teams = [];
        $transferDateFieldId = (int) data_get($config, 'transfer_date_field_id', self::TRANSFER_DATE_FIELD_ID);
        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        $devCallback = function ($leads) use (&$teams, &$allSourceNames, &$totalLeads, $recruiterField, $teamField, $projectField, $cityField, $vacancyField, $sourceField, $recruiterEnumIdsByValue, $teamEnumIdsByValue, $projectEnumIdsByValue, $cityEnumIdsByValue, $vacancyEnumIdsByValue, $sourceEnumIdsByValue): void {
            foreach ($leads as $lead) {
                $customFields = $lead->custom_fields_values ?? [];
                if ($recruiterField !== null && $this->recruiterEnumIds($customFields, (int) $recruiterField->amo_field_id, $recruiterField->name, $recruiterEnumIdsByValue) === []) {
                    continue;
                }
                $teamValues = $teamField ? $this->fieldValueLabels($customFields, (int) $teamField->amo_field_id, $teamField->name, $teamEnumIdsByValue) : [];
                $cityValues = $cityField ? $this->fieldValueLabels($customFields, (int) $cityField->amo_field_id, $cityField->name, $cityEnumIdsByValue) : [];
                $projectValues = $projectField ? $this->fieldValueLabels($customFields, (int) $projectField->amo_field_id, $projectField->name, $projectEnumIdsByValue) : [];
                $vacancyValues = $vacancyField ? $this->fieldValueLabels($customFields, (int) $vacancyField->amo_field_id, $vacancyField->name, $vacancyEnumIdsByValue) : [];
                $sourceValues = $sourceField ? $this->fieldValueLabels($customFields, (int) $sourceField->amo_field_id, $sourceField->name, $sourceEnumIdsByValue) : [];
                $teamKeys = $teamValues ?: ['—'];
                $projectKeys = $projectValues ?: ['Без проекта'];
                $cityKeys = $cityValues ?: ['—'];
                $vacancyKeys = $vacancyValues ?: ['—'];
                $sourceKeys = $sourceValues ?: ['—'];
                foreach ($sourceKeys as $sourceName) { $allSourceNames[$sourceName] = true; }
                $totalLeads++;
                foreach ($teamKeys as $teamName) {
                    $teams[$teamName] ??= ['name' => $teamName, 'total_leads_count' => 0, 'projects' => []];
                    $teams[$teamName]['total_leads_count']++;
                    foreach ($projectKeys as $projectName) {
                        $teams[$teamName]['projects'][$projectName] ??= ['name' => $projectName, 'total_leads_count' => 0, 'cities' => []];
                        $teams[$teamName]['projects'][$projectName]['total_leads_count']++;
                        foreach ($cityKeys as $cityName) {
                            $teams[$teamName]['projects'][$projectName]['cities'][$cityName] ??= ['name' => $cityName, 'leads_count' => 0, 'vacancies' => []];
                            $teams[$teamName]['projects'][$projectName]['cities'][$cityName]['leads_count']++;
                            foreach ($vacancyKeys as $vacancyName) {
                                $teams[$teamName]['projects'][$projectName]['cities'][$cityName]['vacancies'][$vacancyName] ??= ['leads_count' => 0, 'sources' => []];
                                $teams[$teamName]['projects'][$projectName]['cities'][$cityName]['vacancies'][$vacancyName]['leads_count']++;
                                foreach ($sourceKeys as $sourceName) {
                                    $teams[$teamName]['projects'][$projectName]['cities'][$cityName]['vacancies'][$vacancyName]['sources'][$sourceName] ??= 0;
                                    $teams[$teamName]['projects'][$projectName]['cities'][$cityName]['vacancies'][$vacancyName]['sources'][$sourceName]++;
                                }
                            }
                        }
                    }
                }
            }
        };

        CrmEntitySnapshot::query()
            ->select(['id', 'external_id', 'name', 'entity_created_at', 'custom_fields_values'])
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->when($pipelineId > 0, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->orderBy('id')
            ->chunkById(500, function ($leads) use ($devCallback, $transferDateFieldId, $fromDate, $toDate, $timezone, $recruiterField, $recruiterEnumIdsByValue, $managerField, $managerEnumIdsByValue): void {
                $filtered = $leads->filter(function ($lead) use ($transferDateFieldId, $fromDate, $toDate, $timezone, $recruiterField, $recruiterEnumIdsByValue, $managerField, $managerEnumIdsByValue): bool {
                    $customFields = $lead->custom_fields_values ?? [];
                    if ($recruiterField !== null && $this->recruiterEnumIds($customFields, (int) $recruiterField->amo_field_id, $recruiterField->name, $recruiterEnumIdsByValue) === []) {
                        return false;
                    }
                    $managerFieldId = (int) ($managerField?->amo_field_id ?? 0);
                    $transferDate = $this->transferEffectiveDate($customFields, $transferDateFieldId, $managerFieldId, self::MANAGER_FIELD_NAME, $managerEnumIdsByValue, $lead->entity_created_at, $timezone);
                    return $this->dateInPeriod($transferDate, $fromDate, $toDate);
                });
                $devCallback($filtered);
            });

        $sourceColumns = array_keys($allSourceNames);

        $teamsList = collect($teams)
            ->map(function (array $team): array {
                $team['projects'] = collect($team['projects'])
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
                return $team;
            })
            ->sortByDesc('total_leads_count')
            ->values()
            ->all();

        return array_merge($commonReturn, [
            'source_columns' => $sourceColumns,
            'total_leads_count' => $totalLeads,
            'projects' => [],
            'teams' => $teamsList,
        ]);
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

    private function shiftDateLeadsCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone = 'UTC'): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_shift_date_leads',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
            $timezone,
            data_get($config, 'shift_date_field_id') ?: data_get($config, 'shift_date_field_name', self::SHIFT_DATE_FIELD_NAME),
        ]);
    }

    private function avitoCabinetBreakdownCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_avito_cabinet_breakdown',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
        ]);
    }

    private function managerLeadDistributionCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config, string $timezone = 'UTC'): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_manager_lead_distribution',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
            $timezone,
            data_get($config, 'pipeline_id') ?: 'all',
            data_get($config, 'manager_field_id') ?: data_get($config, 'manager_field_name', self::MANAGER_FIELD_NAME),
            data_get($config, 'success_status_id') ?: 'none',
            data_get($config, 'manager_plan_percent') ?: self::DEFAULT_MANAGER_PLAN_PERCENT,
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

    /**
     * Effective transfer date:
     * - field 1435403 filled → use it
     * - field not filled but manager is set → fall back to entity_created_at
     * - neither → null (not transferred)
     */
    private function transferEffectiveDate(array $customFields, int $transferFieldId, int $managerFieldId, string $managerFieldName, array $managerEnumIdsByValue, ?Carbon $entityCreatedAt, string $timezone): ?Carbon
    {
        $fieldDate = $this->customDateFieldValue($customFields, $transferFieldId, $timezone);
        if ($fieldDate !== null) {
            return $fieldDate;
        }
        if ($managerFieldId > 0 && $this->fieldHasAnyValue($customFields, $managerFieldId, $managerFieldName, $managerEnumIdsByValue)) {
            return $entityCreatedAt?->copy()->setTimezone($timezone);
        }
        return null;
    }

    private function buildCompletedOverdueDashboard(AmoAccount $account, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $users = AmoUsersSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->get()
            ->keyBy('amo_user_id');
        $rows = [];

        CrmEntitySnapshot::query()
            ->select(['id', 'responsible_user_id', 'entity_created_at', 'raw'])
            ->forceIndex('ces_account_type_id')
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

    private function statisticsCacheKey(AmoAccount $account, ?Carbon $from, ?Carbon $to): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_task_statistics',
            $account->id,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
        ]);
    }

    private function userOverdueTasksCacheKey(AmoAccount $account, int $userId, ?Carbon $from, ?Carbon $to): string
    {
        $version = Cache::get($this->dashboardCacheVersionKey($account), 'initial');

        return implode(':', [
            'amo_task_user_overdue_tasks',
            $account->id,
            $userId,
            $version,
            $from?->timestamp ?? 'null',
            $to?->timestamp ?? 'null',
        ]);
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
