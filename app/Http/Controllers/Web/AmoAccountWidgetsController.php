<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\DashboardWidget;
use Illuminate\View\View;

class AmoAccountWidgetsController extends Controller
{
    public function __invoke(AmoAccount $amoAccount): View
    {
        return view('amo-accounts.widgets', [
            'account' => $amoAccount,
            'widgets' => DashboardWidget::query()->orderBy('sort_order')->get(),
        ]);
    }
}
