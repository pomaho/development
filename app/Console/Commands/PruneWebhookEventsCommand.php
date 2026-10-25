<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AmoWebhookEvent;
use Illuminate\Console\Command;

class PruneWebhookEventsCommand extends Command
{
    protected $signature = 'amo:prune-webhook-events {--days=30 : Retention window in days}';

    protected $description = 'Delete processed/skipped amoCRM webhook events older than the retention window';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = 0;

        // Pending and failed events are kept regardless of age — they still need
        // processing or investigating. Deleted in chunks so a large backlog doesn't
        // hold one long lock on a table the webhook receiver writes to constantly.
        do {
            $chunk = AmoWebhookEvent::query()
                ->whereIn('status', [AmoWebhookEvent::STATUS_PROCESSED, AmoWebhookEvent::STATUS_SKIPPED])
                ->where('received_at', '<', now()->subDays($days))
                ->limit(5000)
                ->delete();
            $deleted += $chunk;
        } while ($chunk === 5000);

        $this->info("Pruned {$deleted} webhook event(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
