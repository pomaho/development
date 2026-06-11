<?php

use App\Models\ApiRequestLog;
use Illuminate\Support\Facades\Schedule;

Schedule::command('amo:refresh-tokens')->everyThirtyMinutes();

Schedule::command('amo:run-lead-sync-schedules')->everyFiveMinutes()->withoutOverlapping();

Schedule::call(function (): void {
    ApiRequestLog::query()
        ->where('created_at', '<', now()->subDays(config('amo.api_log_retention_days', 30)))
        ->delete();
})->dailyAt('03:00');
