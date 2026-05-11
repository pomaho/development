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
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoAccountController extends Controller
{
    public function index(): View
    {
        return view('amo-accounts.index', [
            'accounts' => AmoAccount::query()->with('credentials')->latest()->paginate(20),
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

    public function show(AmoAccount $amoAccount): View
    {
        $amoAccount->load('credentials');

        return view('amo-accounts.show', [
            'account' => $amoAccount,
            'usersCount' => $amoAccount->usersSnapshots()->count(),
            'adminsCount' => $amoAccount->usersSnapshots()->where('is_admin', true)->count(),
            'logs' => $amoAccount->apiRequestLogs()->latest()->limit(15)->get(),
        ]);
    }

    public function edit(AmoAccount $amoAccount): View
    {
        $this->authorize('update', $amoAccount);

        return view('amo-accounts.edit', [
            'account' => $amoAccount->load('credentials'),
            'credential' => $amoAccount->credentials,
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
