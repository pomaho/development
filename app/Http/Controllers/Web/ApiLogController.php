<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Services\Exports\TableExportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiLogController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Logs/Api', [
            'logs' => ApiRequestLog::query()
                ->with('account')
                ->latest()
                ->paginate(50)
                ->through(fn (ApiRequestLog $log): array => [
                    'id' => $log->id,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'account_name' => $log->account?->name,
                    'method' => $log->method,
                    'url' => $log->url,
                    'status_code' => $log->status_code,
                    'duration_ms' => $log->duration_ms,
                    'error_message' => $log->error_message,
                    'response_payload' => $log->response_payload ?? [],
                ]),
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'export' => route('logs.api.export'),
                'logout' => route('logout'),
                'current_account' => null,
            ],
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
