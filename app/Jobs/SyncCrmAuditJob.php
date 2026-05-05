<?php

namespace App\Jobs;

use App\Models\AmoAccount;
use App\Services\Amo\CrmAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SyncCrmAuditJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public readonly int $amoAccountId,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly bool $structureOnly = false,
    ) {
    }

    public function handle(CrmAuditService $auditService): void
    {
        $account = AmoAccount::query()->findOrFail($this->amoAccountId);
        $from = $this->from ? Carbon::parse($this->from) : null;
        $to = $this->to ? Carbon::parse($this->to) : null;

        if ($this->structureOnly) {
            $auditService->syncStructure($account);
            return;
        }

        $auditService->syncAll($account, $from, $to);
    }
}
