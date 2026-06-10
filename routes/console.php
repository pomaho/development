<?php

use App\Jobs\SyncAmoUsersAndRolesJob;
use App\Models\AmoAccount;
use App\Models\ApiRequestLog;
use Illuminate\Support\Facades\Schedule;

Schedule::command('amo:refresh-tokens')->everyThirtyMinutes();

Schedule::call(function (): void {
    AmoAccount::query()->active()->pluck('id')->each(
        fn (int $id) => SyncAmoUsersAndRolesJob::dispatch($id)
    );
})->hourlyAt(0);

Schedule::command('amo:sync-task-statistics --mode=incremental')->hourlyAt(10);
Schedule::command('amo:sync-task-statistics --mode=refresh --days=3')->dailyAt('01:20');

Schedule::call(function (): void {
    ApiRequestLog::query()
        ->where('created_at', '<', now()->subDays(config('amo.api_log_retention_days', 30)))
        ->delete();
})->dailyAt('03:00');
