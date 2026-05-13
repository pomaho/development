<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoOAuthConnection;
use App\Services\Amo\AmoExternalOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AmoExternalOAuthController extends Controller
{
    public function install(AmoExternalOAuthService $service): Response
    {
        $connection = $service->createPending();

        return Inertia::render('OAuth/Public/Install', [
            'connection' => [
                'state' => $connection->state,
                'redirect_uri' => $connection->redirect_uri,
                'secrets_uri' => $connection->secrets_uri,
            ],
            'external' => config('amo.external'),
        ]);
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('OAuth/External/Index', [
            'connections' => AmoOAuthConnection::query()
                ->with('account')
                ->latest()
                ->paginate(20)
                ->through(fn (AmoOAuthConnection $connection): array => [
                    'id' => $connection->id,
                    'name' => $connection->name,
                    'base_domain' => $connection->base_domain,
                    'status' => $connection->status,
                    'created_at' => $connection->created_at?->toDateTimeString(),
                    'account' => $connection->account ? [
                        'id' => $connection->account->id,
                        'name' => $connection->account->name,
                        'url' => route('amo-accounts.show', $connection->account),
                    ] : null,
                    'url' => route('amo-oauth.external.show', $connection),
                ]),
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'install' => route('amo-oauth.install'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'current_account' => null,
            ],
        ]);
    }

    public function show(Request $request, AmoOAuthConnection $connection): Response
    {
        abort_unless($request->user()->isAdmin() && (! $connection->owner_user_id || $connection->owner_user_id === $request->user()->id), 403);
        $connection->load('account');

        return Inertia::render('OAuth/External/Show', [
            'connection' => [
                'id' => $connection->id,
                'state' => $connection->state,
                'name' => $connection->name,
                'base_domain' => $connection->base_domain,
                'redirect_uri' => $connection->redirect_uri,
                'secrets_uri' => $connection->secrets_uri,
                'scopes' => $connection->scopes ?? [],
                'status' => $connection->status,
                'error_message' => $connection->error_message,
                'expires_at' => $connection->expires_at?->toDateTimeString(),
                'account' => $connection->account ? [
                    'id' => $connection->account->id,
                    'name' => $connection->account->name,
                    'url' => route('amo-accounts.show', $connection->account),
                ] : null,
            ],
            'external' => config('amo.external'),
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'current_account' => null,
            ],
        ]);
    }

    public function secrets(Request $request, AmoExternalOAuthService $service): JsonResponse
    {
        try {
            $connection = $service->receiveSecrets($request->all());

            return response()->json(['status' => $connection->status]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to process amoCRM OAuth secrets webhook.'], 422);
        }
    }

    public function callback(Request $request, AmoExternalOAuthService $service): Response
    {
        try {
            $connection = $service->receiveCallback($request->query());
            $connection->load('account');
        } catch (Throwable $exception) {
            report($exception);
            $connection = null;
        }

        return Inertia::render('OAuth/Public/Callback', [
            'connection' => $connection ? [
                'status' => $connection->status,
                'error_message' => $connection->error_message,
                'account' => $connection->account ? [
                    'name' => $connection->account->name,
                ] : null,
            ] : null,
            'connectedStatus' => AmoOAuthConnection::STATUS_CONNECTED,
        ]);
    }
}
