<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAmoWebhookEventJob;
use App\Models\AmoAccount;
use App\Services\Amo\Webhooks\AmoWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmoWebhookController extends Controller
{
    public function __invoke(Request $request, string $webhookKey, AmoWebhookService $webhookService): JsonResponse
    {
        $account = AmoAccount::query()
            ->where('webhook_key', $webhookKey)
            ->where('is_active', true)
            ->firstOrFail();

        $events = $webhookService->createEvents($account, $request->all());

        foreach ($events as $event) {
            ProcessAmoWebhookEventJob::dispatch($event->id);
        }

        return response()->json([
            'ok' => true,
            'events_accepted' => count($events),
        ]);
    }
}
