<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AmoUsersController extends Controller
{
    public function __invoke(Request $request, AmoAccount $amoAccount): View
    {
        $query = $amoAccount->usersSnapshots()->latest('synced_at');

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

        return view('amo-accounts.users', [
            'account' => $amoAccount,
            'users' => $query->paginate(50)->withQueryString(),
            'roles' => $amoAccount->usersSnapshots()->whereNotNull('role_id')->distinct()->orderBy('role_id')->pluck('role_id'),
            'groups' => $amoAccount->usersSnapshots()->whereNotNull('group_id')->distinct()->orderBy('group_id')->pluck('group_id'),
        ]);
    }
}
