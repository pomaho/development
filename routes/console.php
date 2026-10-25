<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('amo:refresh-tokens')->everyThirtyMinutes();

Schedule::command('amo:run-lead-sync-schedules')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('amo:prune-api-logs')->dailyAt('03:00');

Schedule::command('amo:prune-webhook-events')->dailyAt('03:10');

Schedule::command('amo:check-sync-health')->everyThirtyMinutes();

// Keeps custom field enum labels (managers, recruiters, loss reasons, etc.) current —
// without this, a newly added amoCRM field option shows as a raw "Менеджер {id}" fallback
// in reports until someone runs amo:crm-audit --structure-only by hand.
Schedule::command('amo:crm-audit --structure-only')->dailyAt('04:00')->withoutOverlapping();

// New amoCRM users only show up by name in reports once synced; this used to be
// manual-only, so new managers appeared as "Без ответственного" for weeks.
Schedule::command('amo:sync-users')->dailyAt('04:30')->withoutOverlapping();
