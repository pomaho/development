<?php

namespace App\Console\Commands;

use App\Models\LeadSyncSchedule;
use App\Services\Amo\CrmAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class AmoRunLeadSyncSchedulesCommand extends Command
{
    protected $signature = 'amo:run-lead-sync-schedules {--schedule-id=}';

    protected $description = 'Run configured amoCRM lead synchronization schedules.';

    public function handle(CrmAuditService $auditService): int
    {
        $query = LeadSyncSchedule::query()
            ->with('account')
            ->whereHas('account', fn ($accounts) => $accounts->active())
            ->orderBy('next_run_at')
            ->orderBy('id');

        if ($this->option('schedule-id')) {
            $query->whereKey((int) $this->option('schedule-id'));
        } else {
            $query->due();
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->components->info('No due lead sync schedules.');

            return self::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            $this->runSchedule($schedule, $auditService);
        }

        return self::SUCCESS;
    }

    private function runSchedule(LeadSyncSchedule $schedule, CrmAuditService $auditService): void
    {
        $startedAt = now();
        $from = $startedAt->copy()->subDays($schedule->lookback_days)->startOfDay();
        $to = $startedAt->copy()->endOfDay();

        $schedule->forceFill([
            'last_run_at' => $startedAt,
            'last_finished_at' => null,
            'last_status' => LeadSyncSchedule::STATUS_RUNNING,
            'last_error' => null,
        ])->save();

        try {
            $counts = $auditService->syncAll(
                $schedule->account,
                $from,
                $to,
                $schedule->amo_pipeline_id
            );

            $schedule->forceFill([
                'last_finished_at' => now(),
                'next_run_at' => $this->nextRunAt($schedule),
                'last_status' => LeadSyncSchedule::STATUS_COMPLETED,
                'last_synced_count' => (int) ($counts['leads'] ?? 0),
                'last_error' => null,
            ])->save();

            $this->components->info("Schedule {$schedule->id}: synced {$schedule->last_synced_count} leads.");
        } catch (Throwable $exception) {
            $schedule->forceFill([
                'last_finished_at' => now(),
                'next_run_at' => $this->nextRunAt($schedule),
                'last_status' => LeadSyncSchedule::STATUS_FAILED,
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            Log::error('Lead sync schedule failed.', [
                'lead_sync_schedule_id' => $schedule->id,
                'amo_account_id' => $schedule->amo_account_id,
                'amo_pipeline_id' => $schedule->amo_pipeline_id,
                'exception' => $exception,
            ]);

            $this->components->error("Schedule {$schedule->id}: {$exception->getMessage()}");
        }
    }

    private function nextRunAt(LeadSyncSchedule $schedule): Carbon
    {
        return now()->addMinutes($schedule->interval_minutes);
    }
}
