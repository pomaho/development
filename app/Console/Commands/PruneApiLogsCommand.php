<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use Illuminate\Console\Command;

class PruneApiLogsCommand extends Command
{
    protected $signature = 'amo:prune-api-logs {--days= : Retention window in days (default: amo.api_log_retention_days config)}';

    protected $description = 'Delete API request logs older than the configured retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('amo.api_log_retention_days', 30));

        $deleted = ApiRequestLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} API log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
