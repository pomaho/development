<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\IntegrationModule;
use Illuminate\View\View;

class AmoAccountIntegrationsController extends Controller
{
    public function __invoke(AmoAccount $amoAccount): View
    {
        return view('amo-accounts.integrations', [
            'account' => $amoAccount,
            'modules' => IntegrationModule::query()->orderBy('name')->get(),
        ]);
    }
}
