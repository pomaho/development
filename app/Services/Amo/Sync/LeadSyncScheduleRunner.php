<?php

namespace App\Services\Amo\Sync;

use App\Models\LeadSyncSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeadSyncScheduleRunner
{
    /**
     * Overlap subtracted from the stored watermark when resuming a cursored sync,
     * as a safety margin against clock skew / late-arriving writes at the boundary.
     */
    private const WATERMARK_OVERLAP_MINUTES = 30;

    public function __construct(private readonly CrmAuditService $auditService)
    {
    }

    public function run(LeadSyncSchedule $schedule, ?int $lookbackDays = null, bool $advanceNextRun = true): int
    {
        $startedAt = now();

        // Leads synced by updated_at track a persistent watermark instead of a
        // rolling "now - lookback_days" window, so a sync outage (or a period
        // before this cursor existed) can never permanently skip updates — the
        // next run just resumes from where coverage last left off. An explicit
        // $lookbackDays override (e.g. a manual backfill) always uses the wider
        // rolling window instead, ignoring the watermark for that one run.
        $isCursoredLeadsSync = $schedule->entity_type === LeadSyncSchedule::ENTITY_TYPE_LEADS && $schedule->use_updated_at;
        $useWatermark = $isCursoredLeadsSync && $lookbackDays === null && $schedule->synced_watermark_at !== null;

        $from = $useWatermark
            ? $schedule->synced_watermark_at->copy()->subMinutes(self::WATERMARK_OVERLAP_MINUTES)
            : $startedAt->copy()->subDays($lookbackDays ?? $schedule->lookback_days)->startOfDay();
        $to = $startedAt->copy()->endOfDay();

        $schedule->forceFill([
            'last_run_at' => $startedAt,
            'last_finished_at' => null,
            'last_status' => LeadSyncSchedule::STATUS_RUNNING,
            'last_error' => null,
        ])->save();

        try {
            if ($schedule->entity_type === LeadSyncSchedule::ENTITY_TYPE_CONTACTS) {
                $counts = $this->auditService->syncContacts($schedule->account, $from, $to);
                $syncedCount = (int) ($counts['contacts'] ?? 0) + (int) ($counts['companies'] ?? 0);
            } elseif ($isCursoredLeadsSync) {
                $counts = $this->auditService->syncRecentlyUpdatedLeads(
                    $schedule->account,
                    $from,
                    $to,
                    $schedule->amo_pipeline_id
                );
                $syncedCount = (int) ($counts['leads'] ?? 0);
            } else {
                $counts = $this->auditService->syncOperationalData(
                    $schedule->account,
                    $from,
                    $to,
                    $schedule->amo_pipeline_id
                );
                $syncedCount = (int) ($counts['leads'] ?? 0);
            }

            $schedule->forceFill([
                'last_finished_at' => now(),
                'next_run_at' => $advanceNextRun ? $this->nextRunAt($schedule) : $schedule->next_run_at,
                'last_status' => LeadSyncSchedule::STATUS_COMPLETED,
                'last_synced_count' => $syncedCount,
                'last_error' => null,
                'synced_watermark_at' => $isCursoredLeadsSync ? $startedAt : $schedule->synced_watermark_at,
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
