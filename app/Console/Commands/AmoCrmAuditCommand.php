<?php

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Services\Amo\CrmAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AmoCrmAuditCommand extends Command
{
    protected $signature = 'amo:crm-audit {accountId?} {--from=} {--to=} {--structure-only}';
    protected $description = 'Sync CRM audit snapshots: pipelines, fields, leads, contacts, events, tasks and more.';

    public function handle(CrmAuditService $auditService): int
    {
        $query = AmoAccount::query()->active();

        if ($this->argument('accountId')) {
            $query->whereKey((int) $this->argument('accountId'));
        }

        $from = $this->option('from') ? Carbon::parse($this->option('from')) : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : null;

        foreach ($query->get() as $account) {
            $this->components->task("CRM audit {$account->base_domain}", function () use ($auditService, $account, $from, $to): void {
                $this->option('structure-only')
                    ? $auditService->syncStructure($account)
                    : $auditService->syncAll($account, $from, $to);
            });
        }

        return self::SUCCESS;
    }
}
