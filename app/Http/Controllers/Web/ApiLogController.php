<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use Illuminate\View\View;

class ApiLogController extends Controller
{
    public function __invoke(): View
    {
        return view('logs.api', [
            'logs' => ApiRequestLog::query()->with('account')->latest()->paginate(50),
        ]);
    }
}
