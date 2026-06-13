<?php

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Services\Amo\Sync\CrmAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AmoCrmAuditCommand extends Command
{
    protected $signature = 'amo:crm-audit {accountId?} {--from=} {--to=} {--structure-only} {--pipeline-id=}';
    protected $description = 'Sync CRM audit snapshots: pipelines, fields, leads, contacts, events, tasks and more.';

    public function handle(CrmAuditService $auditService): int
    {
        $query = AmoAccount::query()->active();

        if ($this->argument('accountId')) {
            $query->whereKey((int) $this->argument('accountId'));
        }

        $from = $this->option('from') ? Carbon::parse($this->option('from')) : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : null;
        $pipelineId = $this->option('pipeline-id') ? (int) $this->option('pipeline-id') : null;

        foreach ($query->get() as $account) {
            $label = $pipelineId ? "CRM audit {$account->base_domain} pipeline {$pipelineId}" : "CRM audit {$account->base_domain}";
            $this->components->task($label, function () use ($auditService, $account, $from, $to, $pipelineId): void {
                $this->option('structure-only')
                    ? $auditService->syncStructure($account, $pipelineId)
                    : $auditService->syncAll($account, $from, $to, $pipelineId);
            });
        }

        return self::SUCCESS;
    }
}
