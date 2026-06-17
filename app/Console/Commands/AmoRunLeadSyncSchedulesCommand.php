<?php

namespace App\Console\Commands;

use App\Jobs\SyncAmoTaskStatisticsJob;
use App\Models\LeadSyncSchedule;
use App\Models\TaskStatisticsSyncRun;
use App\Services\Amo\Sync\LeadSyncScheduleRunner;
use Illuminate\Console\Command;
use Throwable;

class AmoRunLeadSyncSchedulesCommand extends Command
{
    protected $signature = 'amo:run-lead-sync-schedules {--schedule-id=}';

    protected $description = 'Run configured amoCRM synchronization schedules.';

    public function handle(LeadSyncScheduleRunner $runner): int
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
            $this->components->info('No due sync schedules.');

            return self::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            if ($schedule->entity_type === LeadSyncSchedule::ENTITY_TYPE_LEADS) {
                $this->runLeadsSchedule($schedule, $runner);
            } else {
                $this->runNonLeadsSchedule($schedule);
            }
        }

        return self::SUCCESS;
    }

    private function runLeadsSchedule(LeadSyncSchedule $schedule, LeadSyncScheduleRunner $runner): void
    {
        try {
            $syncedCount = $runner->run($schedule);

            $this->components->info("Schedule {$schedule->id} (leads): synced {$syncedCount} leads.");
        } catch (Throwable $exception) {
            $this->components->error("Schedule {$schedule->id} (leads): {$exception->getMessage()}");
        }
    }

    private function runNonLeadsSchedule(LeadSyncSchedule $schedule): void
    {
        try {
            $to = now()->endOfDay();
            $from = $to->copy()->subDays($schedule->lookback_days - 1)->startOfDay();

            $run = TaskStatisticsSyncRun::query()->create([
                'amo_account_id' => $schedule->amo_account_id,
                'status' => TaskStatisticsSyncRun::STATUS_PENDING,
                'period_from' => $from,
                'period_to' => $to,
            ]);

            SyncAmoTaskStatisticsJob::dispatch($run->id);

            $schedule->forceFill([
                'last_run_at' => now(),
                'last_finished_at' => now(),
                'next_run_at' => now()->addMinutes($schedule->interval_minutes),
                'last_status' => LeadSyncSchedule::STATUS_COMPLETED,
                'last_synced_count' => null,
                'last_error' => null,
            ])->save();

            $this->components->info("Schedule {$schedule->id} ({$schedule->entity_type}): job #{$run->id} dispatched.");
        } catch (Throwable $exception) {
            $schedule->forceFill([
                'last_finished_at' => now(),
                'next_run_at' => now()->addMinutes($schedule->interval_minutes),
                'last_status' => LeadSyncSchedule::STATUS_FAILED,
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            $this->components->error("Schedule {$schedule->id} ({$schedule->entity_type}): {$exception->getMessage()}");
        }
    }
}
