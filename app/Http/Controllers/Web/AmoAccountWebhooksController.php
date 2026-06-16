<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteAmoWebhookRequest;
use App\Http\Requests\StoreAmoWebhookRequest;
use App\Http\Requests\UpdateAmoWebhookRequest;
use App\Models\AmoAccount;
use App\Services\Amo\Webhooks\AmoWebhooksRegistrationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AmoAccountWebhooksController extends Controller
{
    public function __construct(private readonly AmoWebhooksRegistrationService $service)
    {
    }

    public function index(AmoAccount $amoAccount): Response
    {
        $this->authorize('sync', $amoAccount);

        $webhooks = [];
        $fetchError = null;

        try {
            $webhooks = $this->service->list($amoAccount);
        } catch (Throwable $exception) {
            $fetchError = $exception->getMessage();
        }

        return Inertia::render('AmoAccounts/Webhooks/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'webhooks' => $webhooks,
            'incomingUrl' => route('webhooks.amo', $amoAccount->webhook_key),
            'availableEvents' => AmoWebhooksRegistrationService::AVAILABLE_EVENTS,
            'fetchError' => $fetchError,
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'catalogs' => route('amo-accounts.catalogs.index', $amoAccount),
                    'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                    'webhooks' => route('amo-accounts.webhooks.index', $amoAccount),
                ],
            ],
        ]);
    }

    public function store(StoreAmoWebhookRequest $request, AmoAccount $amoAccount): RedirectResponse
    {
        try {
            $this->service->register(
                $amoAccount,
                $request->string('destination')->toString(),
                $request->validated('events'),
            );
        } catch (Throwable $exception) {
            return back()->with('error', 'Не удалось зарегистрировать вебхук: ' . $exception->getMessage());
        }

        return back()->with('success', 'Вебхук успешно зарегистрирован в amoCRM.');
    }

    public function update(UpdateAmoWebhookRequest $request, AmoAccount $amoAccount): RedirectResponse
    {
        try {
            $this->service->unsubscribe($amoAccount, $request->string('old_destination')->toString());
            $this->service->register(
                $amoAccount,
                $request->string('destination')->toString(),
                $request->validated('events'),
            );
        } catch (Throwable $exception) {
            return back()->with('error', 'Не удалось обновить вебхук: ' . $exception->getMessage());
        }

        return back()->with('success', 'Вебхук обновлён.');
    }

    public function destroy(DeleteAmoWebhookRequest $request, AmoAccount $amoAccount): RedirectResponse
    {
        try {
            $this->service->unsubscribe(
                $amoAccount,
                $request->string('destination')->toString(),
            );
        } catch (Throwable $exception) {
            return back()->with('error', 'Не удалось удалить вебхук: ' . $exception->getMessage());
        }

        return back()->with('success', 'Вебхук удалён из amoCRM.');
    }
}
