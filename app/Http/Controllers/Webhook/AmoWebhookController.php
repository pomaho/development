<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAmoWebhookEventJob;
use App\Models\AmoAccount;
use App\Services\Amo\Webhooks\AmoWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmoWebhookController extends Controller
{
    private const PROCESSING_DELAY_SECONDS = 60;

    public function __invoke(Request $request, string $webhookKey, AmoWebhookService $webhookService): JsonResponse
    {
        $account = AmoAccount::query()
            ->where('webhook_key', $webhookKey)
            ->where('is_active', true)
            ->firstOrFail();

        $events = DB::transaction(function () use ($account, $request, $webhookService): array {
            $created = $webhookService->createEvents($account, $request->all());

            foreach ($created as $event) {
                ProcessAmoWebhookEventJob::dispatch($event->id)
                    ->delay(now()->addSeconds(self::PROCESSING_DELAY_SECONDS))
                    ->afterCommit();
            }

            return $created;
        });

        return response()->json([
            'ok' => true,
            'events_accepted' => count($events),
        ]);
    }
}
