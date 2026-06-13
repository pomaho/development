<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAmoAccountRequest;
use App\Jobs\SyncAmoUsersAndRolesJob;
use App\Models\AmoAccount;
use App\Services\Amo\AmoFallbackHttpClient;
use App\Services\Exports\TableExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoAccountController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('AmoAccounts/Index', [
            'accounts' => AmoAccount::query()
                ->with('credentials')
                ->latest()
                ->paginate(20)
                ->through(fn (AmoAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'base_domain' => $account->base_domain,
                    'auth_type' => $account->credentials?->auth_type,
                    'is_active' => $account->is_active,
                    'auth_status' => $account->auth_status,
                    'last_successful_sync_at' => $account->last_successful_sync_at?->toDateTimeString(),
                    'links' => [
                        'show' => route('amo-accounts.show', $account),
                        'edit' => route('amo-accounts.edit', $account),
                        'test' => route('amo-accounts.test', $account),
                        'sync' => route('amo-accounts.sync', $account),
                        'destroy' => route('amo-accounts.destroy', $account),
                    ],
                    'can' => [
                        'sync' => $request->user()?->can('sync', $account) ?? false,
                        'update' => $request->user()?->can('update', $account) ?? false,
                        'delete' => $request->user()?->can('delete', $account) ?? false,
                    ],
                ]),
            'can' => [
                'create' => $request->user()?->can('create', AmoAccount::class) ?? false,
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'install' => route('amo-oauth.install'),
                'export' => route('amo-accounts.export'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'current_account' => null,
            ],
        ]);
    }

    public function export(TableExportService $export): StreamedResponse
    {
        $accounts = AmoAccount::query()->with('credentials')->latest()->get();

        return $export->csv('amo-accounts.csv', [
            'Название',
            'Домен',
            'Auth',
            'Активен',
            'Статус',
            'Последняя синхронизация',
        ], $accounts->map(fn (AmoAccount $account): array => [
            $account->name,
            $account->base_domain,
            $account->credentials?->auth_type,
            $account->is_active,
            $account->auth_status,
            $account->last_successful_sync_at,
        ]));
    }

    public function show(AmoAccount $amoAccount): Response
    {
        $amoAccount->load('credentials');

        return Inertia::render('AmoAccounts/Show', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
                'account_id' => $amoAccount->account_id,
                'is_active' => $amoAccount->is_active,
                'auth_status' => $amoAccount->auth_status,
                'auth_type' => $amoAccount->credentials?->auth_type,
                'webhook_url' => request()->user()?->can('sync', $amoAccount)
                    ? route('webhooks.amo', $amoAccount->webhook_key)
                    : null,
                'last_successful_sync_at' => $amoAccount->last_successful_sync_at?->toDateTimeString(),
                'settings' => is_array($amoAccount->settings) ? $amoAccount->settings : [],
            ],
            'summary' => [
                'users_count' => $amoAccount->usersSnapshots()->count(),
                'admins_count' => $amoAccount->usersSnapshots()->where('is_admin', true)->count(),
            ],
            'logs' => $amoAccount->apiRequestLogs()
                ->latest()
                ->limit(15)
                ->get()
                ->map(fn ($log): array => [
                    'id' => $log->id,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'method' => $log->method,
                    'status_code' => $log->status_code,
                    'url' => $log->url,
                    'error_message' => $log->error_message,
                ]),
            'can' => [
                'sync' => request()->user()?->can('sync', $amoAccount) ?? false,
                'update' => request()->user()?->can('update', $amoAccount) ?? false,
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'edit' => route('amo-accounts.edit', $amoAccount),
                    'test' => route('amo-accounts.test', $amoAccount),
                    'sync' => route('amo-accounts.sync', $amoAccount),
                    'deactivate' => route('amo-accounts.deactivate', $amoAccount),
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

    public function edit(AmoAccount $amoAccount): Response
    {
        $this->authorize('update', $amoAccount);
        $amoAccount->load('credentials');

        return Inertia::render('AmoAccounts/Edit', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
                'is_active' => $amoAccount->is_active,
                'notes' => $amoAccount->notes,
            ],
            'credential' => [
                'auth_type' => $amoAccount->credentials?->auth_type,
                'masked_access_token' => $amoAccount->credentials?->maskedAccessToken(),
                'redirect_uri' => $amoAccount->credentials?->redirect_uri,
                'token_expires_at' => $amoAccount->credentials?->token_expires_at?->format('Y-m-d\TH:i'),
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'edit' => route('amo-accounts.edit', $amoAccount),
                    'update' => route('amo-accounts.update', $amoAccount),
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

    public function update(StoreAmoAccountRequest $request, AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('update', $amoAccount);

        $amoAccount->update([
            'name' => $request->string('name'),
            'base_domain' => $request->string('base_domain'),
            'is_active' => $request->boolean('is_active'),
            'notes' => $request->input('notes'),
        ]);

        $this->saveCredentials($amoAccount, $request, true);

        return redirect()->route('amo-accounts.show', $amoAccount)->with('status', 'Аккаунт обновлен.');
    }

    public function destroy(AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('delete', $amoAccount);
        $amoAccount->delete();

        return redirect()->route('amo-accounts.index')->with('status', 'Аккаунт удален.');
    }

    public function test(AmoAccount $amoAccount, AmoFallbackHttpClient $http): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);
        $payload = $http->get($amoAccount, '/api/v4/account');

        $amoAccount->forceFill([
            'account_id' => $payload['id'] ?? $amoAccount->account_id,
            'auth_status' => 'ok',
        ])->save();

        return back()->with('status', 'Соединение проверено.');
    }

    public function sync(AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);
        SyncAmoUsersAndRolesJob::dispatch($amoAccount->id);

        return back()->with('status', 'Синхронизация поставлена в очередь.');
    }

    public function deactivate(AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('update', $amoAccount);
        $amoAccount->forceFill(['is_active' => false])->save();

        return back()->with('status', 'Аккаунт деактивирован.');
    }

    private function saveCredentials(AmoAccount $account, Request $request, bool $keepExistingSecrets = false): void
    {
        $existing = $account->credentials;
        $payload = [
            'auth_type' => $request->input('auth_type'),
            'redirect_uri' => $request->input('redirect_uri'),
            'token_expires_at' => $request->input('token_expires_at'),
        ];

        foreach (['access_token', 'refresh_token', 'client_id', 'client_secret'] as $field) {
            if ($request->filled($field)) {
                $payload[$field] = $request->input($field);
            } elseif (! $keepExistingSecrets) {
                $payload[$field] = null;
            }
        }

        $account->credentials()->updateOrCreate(
            ['amo_account_id' => $account->id],
            $payload + ($existing ? [] : ['access_token' => $request->input('access_token')])
        );
    }
}
