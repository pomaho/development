<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoOAuthConnection;
use App\Services\Amo\AmoExternalOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AmoExternalOAuthController extends Controller
{
    public function install(AmoExternalOAuthService $service): View
    {
        return view('amo-oauth.external.install', [
            'connection' => $service->createPending(),
            'external' => config('amo.external'),
        ]);
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('amo-oauth.external.index', [
            'connections' => AmoOAuthConnection::query()
                ->with('account')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Request $request, AmoOAuthConnection $connection): View
    {
        abort_unless($request->user()->isAdmin() && (! $connection->owner_user_id || $connection->owner_user_id === $request->user()->id), 403);

        return view('amo-oauth.external.show', [
            'connection' => $connection,
            'external' => config('amo.external'),
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

    public function callback(Request $request, AmoExternalOAuthService $service): View
    {
        try {
            $connection = $service->receiveCallback($request->query());
        } catch (Throwable $exception) {
            report($exception);
            $connection = null;
        }

        return view('amo-oauth.external.callback', ['connection' => $connection]);
    }
}
