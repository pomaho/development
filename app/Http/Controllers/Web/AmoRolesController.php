<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoRolesSnapshot;
use App\Services\Exports\TableExportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoRolesController extends Controller
{
    public function __invoke(AmoAccount $amoAccount): Response
    {
        return Inertia::render('AmoAccounts/Roles', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'roles' => $amoAccount->rolesSnapshots()
                ->latest('synced_at')
                ->paginate(50)
                ->through(fn (AmoRolesSnapshot $role): array => [
                    'id' => $role->id,
                    'amo_role_id' => $role->amo_role_id,
                    'name' => $role->name,
                    'rights' => $role->rights ?? [],
                    'users_count' => is_array($role->users) ? count($role->users) : 0,
                    'synced_at' => $role->synced_at?->toDateTimeString(),
                ]),
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'export' => route('amo-accounts.roles.export', $amoAccount),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                ],
            ],
        ]);
    }

    public function export(AmoAccount $amoAccount, TableExportService $export): StreamedResponse
    {
        $roles = $amoAccount->rolesSnapshots()->latest('synced_at')->get();

        return $export->csv("amo-roles-{$amoAccount->id}.csv", [
            'ID роли',
            'Название',
            'Пользователей',
            'Права',
            'Дата синхронизации',
        ], $roles->map(fn (AmoRolesSnapshot $role): array => [
            $role->amo_role_id,
            $role->name,
            is_array($role->users) ? count($role->users) : 0,
            $role->rights,
            $role->synced_at,
        ]));
    }
}
