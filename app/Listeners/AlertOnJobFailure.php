<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Alerts\TelegramNotifier;
use Illuminate\Queue\Events\JobFailed;

final class AlertOnJobFailure
{
    public function __construct(private readonly TelegramNotifier $notifier)
    {
    }

    public function handle(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();
        $message = mb_substr($event->exception->getMessage(), 0, 500);

        $this->notifier->sendThrottled(
            "job_failed:{$jobName}:{$message}",
            "🔴 Задача очереди упала\n\n".
            "Job: {$jobName}\n".
            "Очередь: {$event->job->getQueue()}\n".
            "Ошибка: {$message}"
        );
    }
}
