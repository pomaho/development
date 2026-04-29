<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use Illuminate\View\View;

class AmoRolesController extends Controller
{
    public function __invoke(AmoAccount $amoAccount): View
    {
        return view('amo-accounts.roles', [
            'account' => $amoAccount,
            'roles' => $amoAccount->rolesSnapshots()->latest('synced_at')->paginate(50),
        ]);
    }
}
