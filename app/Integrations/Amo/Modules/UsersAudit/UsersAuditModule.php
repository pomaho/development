<?php

namespace App\Integrations\Amo\Modules\UsersAudit;

class UsersAuditModule
{
    public function code(): string
    {
        return 'users_audit';
    }

    public function name(): string
    {
        return 'Users Audit';
    }

    public function routes(): array
    {
        return [
            'amo-accounts.users',
            'amo-accounts.roles',
        ];
    }

    public function dashboardWidgets(): array
    {
        return [
            'users_count',
            'admins_count',
            'last_sync_status',
        ];
    }
}
