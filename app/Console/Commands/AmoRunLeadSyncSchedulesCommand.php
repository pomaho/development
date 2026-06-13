<?php

namespace App\Console\Commands;

use App\Models\LeadSyncSchedule;
use App\Services\Amo\Sync\LeadSyncScheduleRunner;
use Illuminate\Console\Command;
use Throwable;

class AmoRunLeadSyncSchedulesCommand extends Command
{
    protected $signature = 'amo:run-lead-sync-schedules {--schedule-id=}';

    protected $description = 'Run configured amoCRM lead synchronization schedules.';

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
            $this->components->info('No due lead sync schedules.');

            return self::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            $this->runSchedule($schedule, $runner);
        }

        return self::SUCCESS;
    }

    private function runSchedule(LeadSyncSchedule $schedule, LeadSyncScheduleRunner $runner): void
    {
        try {
            $syncedCount = $runner->run($schedule);

            $this->components->info("Schedule {$schedule->id}: synced {$syncedCount} leads.");
        } catch (Throwable $exception) {
            $this->components->error("Schedule {$schedule->id}: {$exception->getMessage()}");
        }
    }
}
