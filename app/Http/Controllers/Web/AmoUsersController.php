<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Services\Exports\TableExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoUsersController extends Controller
{
    public function __invoke(Request $request, AmoAccount $amoAccount): View
    {
        $query = $this->filteredQuery($request, $amoAccount)->latest('synced_at');

        return view('amo-accounts.users', [
            'account' => $amoAccount,
            'users' => $query->paginate(50)->withQueryString(),
            'roles' => $amoAccount->usersSnapshots()->whereNotNull('role_id')->distinct()->orderBy('role_id')->pluck('role_id'),
            'groups' => $amoAccount->usersSnapshots()->whereNotNull('group_id')->distinct()->orderBy('group_id')->pluck('group_id'),
        ]);
    }

    public function export(Request $request, AmoAccount $amoAccount, TableExportService $export): StreamedResponse
    {
        $users = $this->filteredQuery($request, $amoAccount)->latest('synced_at')->get();

        return $export->csv("amo-users-{$amoAccount->id}.csv", [
            'ID amoCRM',
            'Имя',
            'Email',
            'Активен',
            'Админ',
            'Role',
            'Group',
            'Сделки',
            'Контакты',
            'Компании',
            'Задачи',
            'Почта',
            'Каталоги',
            'Дата синхронизации',
        ], $users->map(function (AmoUsersSnapshot $user): array {
            $rights = $user->rights ?? [];

            return [
                $user->amo_user_id,
                $user->name,
                $user->email,
                $user->is_active,
                $user->is_admin,
                $user->role_id,
                $user->group_id,
                $rights['leads'] ?? null,
                $rights['contacts'] ?? null,
                $rights['companies'] ?? null,
                $rights['tasks'] ?? null,
                $rights['mail_access'] ?? $rights['mail'] ?? null,
                $rights['catalogs'] ?? null,
                $user->synced_at,
            ];
        }));
    }

    private function filteredQuery(Request $request, AmoAccount $amoAccount)
    {
        $query = $amoAccount->usersSnapshots();

        if ($request->boolean('admins')) {
            $query->where('is_admin', true);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->input('active') === '1');
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search));
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->input('role_id'));
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->input('group_id'));
        }

        return $query;
    }
}
