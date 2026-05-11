<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Services\Exports\TableExportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiLogController extends Controller
{
    public function __invoke(): View
    {
        return view('logs.api', [
            'logs' => ApiRequestLog::query()->with('account')->latest()->paginate(50),
        ]);
    }

    public function export(TableExportService $export): StreamedResponse
    {
        $logs = ApiRequestLog::query()->with('account')->latest()->get();

        return $export->csv('api-logs.csv', [
            'Дата',
            'Аккаунт',
            'Method',
            'URL',
            'Status',
            'Duration ms',
            'Error',
            'Response',
        ], $logs->map(fn (ApiRequestLog $log): array => [
            $log->created_at,
            $log->account?->name,
            $log->method,
            $log->url,
            $log->status_code,
            $log->duration_ms,
            $log->error_message,
            $log->response_payload,
        ]));
    }
}
