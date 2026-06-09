<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Services\Amo\AmoTaskStatisticsService;
use App\Services\Exports\TableExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoTaskStatisticsController extends Controller
{
    public function index(Request $request, AmoAccount $amoAccount, AmoTaskStatisticsService $statisticsService): Response
    {
        [$from, $to] = $this->period($request);

        return Inertia::render('AmoAccounts/TaskStatistics/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'rows' => $statisticsService->statistics($amoAccount, $from, $to),
            'filters' => [
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
            ],
            'can' => [
                'sync' => $request->user()?->can('sync', $amoAccount) ?? false,
            ],
            'links' => $this->links($amoAccount, $request),
        ]);
    }

    public function sync(Request $request, AmoAccount $amoAccount, AmoTaskStatisticsService $statisticsService): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        [$from, $to] = $this->period($request);
        $counts = $statisticsService->sync($amoAccount, $from, $to);

        return redirect()
            ->route('amo-accounts.task-statistics.index', array_merge(['amo_account' => $amoAccount], $request->only(['from', 'to'])))
            ->with('status', "Синхронизировано задач: выполненных {$counts['completed']}, открытых {$counts['open']}.");
    }

    public function export(Request $request, AmoAccount $amoAccount, AmoTaskStatisticsService $statisticsService, TableExportService $export): StreamedResponse
    {
        [$from, $to] = $this->period($request);

        return $export->csv("amo-task-statistics-{$amoAccount->id}.csv", [
            'ID пользователя',
            'Пользователь',
            'Выполнено за период',
            'Открыто сейчас',
            'Просрочено сейчас',
            'Всего в отчете',
            '% просрочки',
        ], collect($statisticsService->statistics($amoAccount, $from, $to))->map(fn (array $row): array => [
            $row['responsible_user_id'],
            $row['responsible_name'],
            $row['completed_count'],
            $row['open_count'],
            $row['overdue_count'],
            $row['total_count'],
            $row['overdue_rate'],
        ]));
    }

    private function period(Request $request): array
    {
        return [
            $request->filled('from') ? $request->date('from')->startOfDay() : now()->startOfMonth(),
            $request->filled('to') ? $request->date('to')->endOfDay() : now()->endOfDay(),
        ];
    }

    private function links(AmoAccount $amoAccount, Request $request): array
    {
        return [
            'dashboard' => route('dashboard'),
            'amo_accounts' => route('amo-accounts.index'),
            'oauth' => route('amo-oauth.external.index'),
            'api_logs' => route('logs.api'),
            'logout' => route('logout'),
            'sync' => route('amo-accounts.task-statistics.sync', $amoAccount),
            'export' => route('amo-accounts.task-statistics.export', array_merge(['amo_account' => $amoAccount], $request->query())),
            'reset' => route('amo-accounts.task-statistics.index', $amoAccount),
            'current_account' => [
                'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                'show' => route('amo-accounts.show', $amoAccount),
                'users' => route('amo-accounts.users', $amoAccount),
                'roles' => route('amo-accounts.roles', $amoAccount),
                'leads' => route('amo-accounts.leads', $amoAccount),
                'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                'catalogs' => route('amo-accounts.catalogs.index', $amoAccount),
                'responsibility_redistribution' => route('amo-accounts.responsibility-redistribution.index', $amoAccount),
                'task_statistics' => route('amo-accounts.task-statistics.index', $amoAccount),
                'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                'integrations' => route('amo-accounts.integrations', $amoAccount),
                'widgets' => route('amo-accounts.widgets', $amoAccount),
            ],
        ];
    }
}
