<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\AmoUsersSnapshot;
use App\Services\Exports\TableExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoUsersController extends Controller
{
    public function __invoke(Request $request, AmoAccount $amoAccount): Response
    {
        $query = $this->filteredQuery($request, $amoAccount)->latest('synced_at');

        return Inertia::render('AmoAccounts/Users', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'users' => $query
                ->paginate(50)
                ->withQueryString()
                ->through(fn (AmoUsersSnapshot $user): array => [
                    'id' => $user->id,
                    'amo_user_id' => $user->amo_user_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'group_id' => $user->group_id,
                    'is_admin' => $user->is_admin,
                    'is_active' => $user->is_active,
                    'rights' => $user->rights ?? [],
                    'raw' => $user->raw ?? [],
                    'synced_at' => $user->synced_at?->toDateTimeString(),
                ]),
            'roles' => $amoAccount->usersSnapshots()->whereNotNull('role_id')->distinct()->orderBy('role_id')->pluck('role_id'),
            'groups' => $amoAccount->usersSnapshots()->whereNotNull('group_id')->distinct()->orderBy('group_id')->pluck('group_id'),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'active' => $request->filled('active') ? $request->input('active') : '',
                'role_id' => $request->filled('role_id') ? (string) $request->input('role_id') : '',
                'group_id' => $request->filled('group_id') ? (string) $request->input('group_id') : '',
                'admins' => $request->boolean('admins'),
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'export' => route('amo-accounts.users.export', array_merge(['amo_account' => $amoAccount], $request->query())),
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
