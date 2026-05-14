<?php

namespace App\Services\Amo;

use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use Illuminate\Support\Collection;

class AmoLeadTransferService
{
    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function plan(AmoAccount $account, int $sourcePipelineId, int $targetPipelineId, array $statusMap = []): array
    {
        $sourceStatuses = $this->statuses($account, $sourcePipelineId);
        $targetStatuses = $this->statuses($account, $targetPipelineId);
        $normalizedTargets = $targetStatuses->keyBy(fn (CrmPipelineStatusSnapshot $status): string => $this->normalizeName($status->name));

        $rows = $sourceStatuses->map(function (CrmPipelineStatusSnapshot $sourceStatus) use ($account, $sourcePipelineId, $targetStatuses, $normalizedTargets, $statusMap): array {
            $sourceStatusId = (int) $sourceStatus->amo_status_id;
            $mappedStatusId = isset($statusMap[$sourceStatusId]) ? (int) $statusMap[$sourceStatusId] : null;
            $targetStatus = $mappedStatusId
                ? $targetStatuses->firstWhere('amo_status_id', $mappedStatusId)
                : $normalizedTargets->get($this->normalizeName($sourceStatus->name));
            $leadCount = CrmEntitySnapshot::query()
                ->where('amo_account_id', $account->id)
                ->where('entity_type', 'leads')
                ->where('pipeline_id', $sourcePipelineId)
                ->where('status_id', $sourceStatusId)
                ->count();

            return [
                'source_status_id' => $sourceStatusId,
                'source_status_name' => $sourceStatus->name,
                'target_status_id' => $targetStatus?->amo_status_id,
                'target_status_name' => $targetStatus?->name,
                'lead_count' => $leadCount,
                'can_transfer' => $leadCount > 0 && $targetStatus !== null,
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'total_leads' => collect($rows)->sum('lead_count'),
            'transferable_leads' => collect($rows)->where('can_transfer', true)->sum('lead_count'),
            'blocked_leads' => collect($rows)->where('can_transfer', false)->sum('lead_count'),
        ];
    }

    public function transfer(AmoAccount $account, int $sourcePipelineId, int $targetPipelineId, array $statusMap): array
    {
        $plan = $this->plan($account, $sourcePipelineId, $targetPipelineId, $statusMap);
        $allowedMap = collect($plan['rows'])
            ->filter(fn (array $row): bool => $row['can_transfer'])
            ->mapWithKeys(fn (array $row): array => [(int) $row['source_status_id'] => (int) $row['target_status_id']])
            ->all();

        if ($allowedMap === []) {
            return ['updated' => 0, 'skipped' => (int) $plan['total_leads'], 'plan' => $plan];
        }

        $updated = 0;
        CrmEntitySnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('entity_type', 'leads')
            ->where('pipeline_id', $sourcePipelineId)
            ->whereIn('status_id', array_keys($allowedMap))
            ->orderBy('id')
            ->chunkById(250, function (Collection $leads) use ($account, $targetPipelineId, $allowedMap, &$updated): void {
                $payload = $leads->map(fn (CrmEntitySnapshot $lead): array => [
                    'id' => (int) $lead->external_id,
                    'pipeline_id' => $targetPipelineId,
                    'status_id' => $allowedMap[(int) $lead->status_id],
                ])->values()->all();

                if ($payload === []) {
                    return;
                }

                $this->http->patch($account, '/api/v4/leads', $payload);

                foreach ($leads as $lead) {
                    $lead->forceFill([
                        'pipeline_id' => $targetPipelineId,
                        'status_id' => $allowedMap[(int) $lead->status_id],
                    ])->save();
                }

                $updated += count($payload);
            });

        return [
            'updated' => $updated,
            'skipped' => max(0, (int) $plan['total_leads'] - $updated),
            'plan' => $plan,
        ];
    }

    private function statuses(AmoAccount $account, int $pipelineId): Collection
    {
        return CrmPipelineStatusSnapshot::query()
            ->where('amo_account_id', $account->id)
            ->where('amo_pipeline_id', $pipelineId)
            ->orderBy('sort')
            ->get();
    }

    private function normalizeName(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }
}
