<?php

namespace App\Jobs;

use App\Models\AmoAccount;
use App\Models\TaskStatisticsSyncRun;
use App\Services\Amo\AmoTaskStatisticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class SyncAmoTaskStatisticsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1200;

    public function __construct(public readonly int $runId)
    {
    }

    public function handle(AmoTaskStatisticsService $statisticsService): void
    {
        $run = TaskStatisticsSyncRun::query()->findOrFail($this->runId);
        $account = AmoAccount::query()->findOrFail($run->amo_account_id);

        $statisticsService->sync($account, $run->period_from, $run->period_to, $run);
    }

    public function failed(Throwable $exception): void
    {
        TaskStatisticsSyncRun::query()
            ->whereKey($this->runId)
            ->update([
                'status' => TaskStatisticsSyncRun::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
    }
}
