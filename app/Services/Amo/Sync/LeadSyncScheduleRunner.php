<?php

namespace App\Services\Amo\Sync;

use App\Models\LeadSyncSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeadSyncScheduleRunner
{
    public function __construct(private readonly CrmAuditService $auditService)
    {
    }

    public function run(LeadSyncSchedule $schedule, ?int $lookbackDays = null, bool $advanceNextRun = true): int
    {
        $startedAt = now();
        $from = $startedAt->copy()->subDays($lookbackDays ?? $schedule->lookback_days)->startOfDay();
        $to = $startedAt->copy()->endOfDay();

        $schedule->forceFill([
            'last_run_at' => $startedAt,
            'last_finished_at' => null,
            'last_status' => LeadSyncSchedule::STATUS_RUNNING,
            'last_error' => null,
        ])->save();

        try {
            $counts = $this->auditService->syncOperationalData(
                $schedule->account,
                $from,
                $to,
                $schedule->amo_pipeline_id
            );
            $syncedCount = (int) ($counts['leads'] ?? 0);

            $schedule->forceFill([
                'last_finished_at' => now(),
                'next_run_at' => $advanceNextRun ? $this->nextRunAt($schedule) : $schedule->next_run_at,
                'last_status' => LeadSyncSchedule::STATUS_COMPLETED,
                'last_synced_count' => $syncedCount,
                'last_error' => null,
            ])->save();

            return $syncedCount;
        } catch (Throwable $exception) {
            $schedule->forceFill([
                'last_finished_at' => now(),
                'next_run_at' => $advanceNextRun ? $this->nextRunAt($schedule) : $schedule->next_run_at,
                'last_status' => LeadSyncSchedule::STATUS_FAILED,
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            Log::error('Lead sync schedule failed.', [
                'lead_sync_schedule_id' => $schedule->id,
                'amo_account_id' => $schedule->amo_account_id,
                'amo_pipeline_id' => $schedule->amo_pipeline_id,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function nextRunAt(LeadSyncSchedule $schedule): Carbon
    {
        return now()->addMinutes($schedule->interval_minutes);
    }
}
