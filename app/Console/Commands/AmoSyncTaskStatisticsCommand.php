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
    private const INCREMENTAL_OVERLAP_HOURS = 2;

    protected $signature = 'amo:sync-task-statistics {accountId?} {--from=} {--to=} {--days=45} {--mode=incremental}';
    protected $description = 'Queue amoCRM task statistics sync for one or all active accounts.';

    public function handle(): int
    {
        if (! in_array($this->option('mode'), ['incremental', 'refresh'], true)) {
            $this->error('Invalid mode. Use incremental or refresh.');

            return self::FAILURE;
        }

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

            [$from, $to] = $this->period($account);
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

    private function period(AmoAccount $account): array
    {
        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'))->endOfDay()
            : now()->endOfDay();

        if ($this->option('from')) {
            return $this->limitedPeriod(Carbon::parse((string) $this->option('from'))->startOfDay(), $to);
        }

        if ($this->option('mode') === 'refresh') {
            return $this->limitedPeriod($to->copy()->subDays(max(1, (int) $this->option('days')) - 1)->startOfDay(), $to);
        }

        $lastSyncedAt = $account->taskStatisticsLastSuccessfulSyncAt();

        if ($lastSyncedAt === null) {
            return $this->limitedPeriod($to->copy()->subDays(self::SYNC_PERIOD_LIMIT_DAYS - 1)->startOfDay(), $to);
        }

        return $this->limitedPeriod($lastSyncedAt->copy()->subHours(self::INCREMENTAL_OVERLAP_HOURS), $to);
    }

    private function limitedPeriod(Carbon $from, Carbon $to): array
    {
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
