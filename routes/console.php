<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('amo:refresh-tokens')->everyThirtyMinutes();

Schedule::command('amo:run-lead-sync-schedules')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('amo:prune-api-logs')->dailyAt('03:00');

Schedule::command('amo:check-sync-health')->everyThirtyMinutes();

// Keeps custom field enum labels (managers, recruiters, loss reasons, etc.) current —
// without this, a newly added amoCRM field option shows as a raw "Менеджер {id}" fallback
// in reports until someone runs amo:crm-audit --structure-only by hand.
Schedule::command('amo:crm-audit --structure-only')->dailyAt('04:00')->withoutOverlapping();
