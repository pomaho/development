<?php

namespace App\Jobs;

use App\Models\AmoAccount;
use App\Models\TaskStatisticsSyncRun;
use App\Services\Amo\Analytics\AmoTaskSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncAmoTaskStatisticsJob implements ShouldQueue
{
    use Queueable;

    // Sync writes are idempotent (updateOrCreate by external_id), so retrying a
    // transient failure (API timeout, etc.) is safe and lets the job self-heal within
    // minutes instead of waiting for the next scheduled run up to 6h later.
    public int $tries = 3;
    public int $timeout = 1200;

    public function __construct(public readonly int $runId)
    {
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(AmoTaskSyncService $syncService): void
    {
        $run = TaskStatisticsSyncRun::query()->findOrFail($this->runId);
        $account = AmoAccount::query()->findOrFail($run->amo_account_id);

        $syncService->sync($account, $run->period_from, $run->period_to, $run);

        if ($run->period_to !== null && $run->period_to->gt($account->taskStatisticsLastSuccessfulSyncAt() ?? now()->subYears(20))) {
            $account->markTaskStatisticsSyncedUntil($run->period_to);
        }
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
