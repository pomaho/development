<?php

namespace App\Jobs;

use App\Models\AmoWebhookEvent;
use App\Services\Amo\Webhooks\AmoWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessAmoWebhookEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly int $webhookEventId)
    {
    }

    public function handle(AmoWebhookService $webhookService): void
    {
        $event = AmoWebhookEvent::query()->findOrFail($this->webhookEventId);

        if ($event->status !== AmoWebhookEvent::STATUS_PENDING) {
            return;
        }

        try {
            $webhookService->process($event);
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => AmoWebhookEvent::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }
}
