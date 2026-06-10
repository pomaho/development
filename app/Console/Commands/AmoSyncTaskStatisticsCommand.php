<?php

namespace App\Console\Commands;

use App\Jobs\SyncAmoTaskStatisticsJob;
use App\Models\AmoAccount;
use App\Models\TaskStatisticsSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AmoSyncTaskStatisticsCommand extends Command
{
    private const SYNC_PERIOD_LIMIT_DAYS = 45;

    protected $signature = 'amo:sync-task-statistics {accountId?} {--from=} {--to=} {--days=45}';
    protected $description = 'Queue amoCRM task statistics sync for one or all active accounts.';

    public function handle(): int
    {
        [$from, $to] = $this->period();
        $query = AmoAccount::query()->active();

        if ($this->argument('accountId')) {
            $query->whereKey((int) $this->argument('accountId'));
        }

        $queued = 0;

        foreach ($query->get() as $account) {
            if ($this->hasFreshQueuedRun($account)) {
                $this->line("Skip {$account->base_domain}: sync is already queued or running.");
                continue;
            }

            $run = TaskStatisticsSyncRun::query()->create([
                'amo_account_id' => $account->id,
                'status' => TaskStatisticsSyncRun::STATUS_PENDING,
                'period_from' => $from,
                'period_to' => $to,
            ]);

            SyncAmoTaskStatisticsJob::dispatch($run->id);
            $queued++;
            $this->line("Queued {$account->base_domain}: run #{$run->id}.");
        }

        $this->info("Queued task statistics sync jobs: {$queued}.");

        return self::SUCCESS;
    }

    private function period(): array
    {
        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'))->endOfDay()
            : now()->endOfDay();

        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : $to->copy()->subDays(max(1, (int) $this->option('days')) - 1)->startOfDay();
        $minFrom = $to->copy()->subDays(self::SYNC_PERIOD_LIMIT_DAYS - 1)->startOfDay();

        if ($from->lt($minFrom)) {
            $from = $minFrom;
        }

        return [$from, $to];
    }

    private function hasFreshQueuedRun(AmoAccount $account): bool
    {
        return $account->taskStatisticsSyncRuns()
            ->whereIn('status', [
                TaskStatisticsSyncRun::STATUS_PENDING,
                TaskStatisticsSyncRun::STATUS_RUNNING,
            ])
            ->where('created_at', '>=', now()->subHours(2))
            ->exists();
    }
}
