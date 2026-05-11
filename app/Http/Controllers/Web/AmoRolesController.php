<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoRolesSnapshot;
use App\Services\Exports\TableExportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoRolesController extends Controller
{
    public function __invoke(AmoAccount $amoAccount): View
    {
        return view('amo-accounts.roles', [
            'account' => $amoAccount,
            'roles' => $amoAccount->rolesSnapshots()->latest('synced_at')->paginate(50),
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
