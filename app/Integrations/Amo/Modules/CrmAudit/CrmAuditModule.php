<?php

namespace App\Integrations\Amo\Modules\CrmAudit;

class CrmAuditModule
{
    public function code(): string
    {
        return 'crm_audit';
    }

    public function name(): string
    {
        return 'CRM Audit';
    }

    public function routes(): array
    {
        return [
            'amo-accounts.crm-audit.index',
            'amo-accounts.crm-audit.sync',
        ];
    }
}
