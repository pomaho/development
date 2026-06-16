<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('amo:refresh-tokens')->everyThirtyMinutes();

Schedule::command('amo:run-lead-sync-schedules')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('amo:prune-api-logs')->dailyAt('03:00');
