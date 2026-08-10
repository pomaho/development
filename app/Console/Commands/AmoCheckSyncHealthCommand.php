<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Models\LeadSyncSchedule;
use App\Models\TaskStatisticsSyncRun;
use App\Services\Alerts\TelegramNotifier;
use App\Services\Amo\Webhooks\AmoWebhooksRegistrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class AmoCheckSyncHealthCommand extends Command
{
    protected $signature = 'amo:check-sync-health';

    protected $description = 'Alert when sync schedules are failing or have gone quiet for longer than expected.';

    public function handle(TelegramNotifier $notifier, AmoWebhooksRegistrationService $webhooksService): int
    {
        $this->reportFailedRuns($notifier);
        $this->reportStaleSchedules($notifier);
        $this->reportDisabledWebhooks($notifier, $webhooksService);

        return self::SUCCESS;
    }

    private function reportFailedRuns(TelegramNotifier $notifier): void
    {
        $runs = TaskStatisticsSyncRun::query()
            ->with('amoAccount')
            ->where('status', TaskStatisticsSyncRun::STATUS_FAILED)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->get();

        foreach ($runs as $run) {
            $domain = $run->amoAccount?->base_domain ?? "id {$run->amo_account_id}";

            $notifier->sendThrottled(
                "sync_run_failed:{$run->amo_account_id}:".mb_substr((string) $run->error_message, 0, 200),
                "🔴 Синхронизация задач упала\n\n".
                "Аккаунт: {$domain}\n".
                "Run #{$run->id}\n".
                'Ошибка: '.mb_substr((string) $run->error_message, 0, 500),
                minutes: 120,
            );
        }
    }

    private function reportStaleSchedules(TelegramNotifier $notifier): void
    {
        $multiplier = (int) config('alerts.stale_schedule_multiplier', 3);

        $schedules = LeadSyncSchedule::query()
            ->with('account')
            ->where('is_enabled', true)
            ->whereHas('account', fn ($accounts) => $accounts->active())
            ->get();

        foreach ($schedules as $schedule) {
            // For tasks/events, last_finished_at reflects when the async job was
            // *dispatched*, not when it actually completed — use the account's
            // tracked successful-sync cursor instead, which only advances on
            // genuine success (see SyncAmoTaskStatisticsJob::handle()).
            $lastFinished = in_array($schedule->entity_type, [LeadSyncSchedule::ENTITY_TYPE_TASKS, LeadSyncSchedule::ENTITY_TYPE_EVENTS], true)
                ? $schedule->account?->taskStatisticsLastSuccessfulSyncAt()
                : $schedule->last_finished_at;

            $expectedEvery = max(1, $schedule->interval_minutes);
            $overdueThreshold = Carbon::now()->subMinutes($expectedEvery * $multiplier);

            if ($lastFinished !== null && $lastFinished->greaterThan($overdueThreshold)) {
                continue;
            }

            $domain = $schedule->account?->base_domain ?? "id {$schedule->amo_account_id}";
            $sinceText = $lastFinished?->diffForHumans() ?? 'ни разу не запускалось';

            $notifier->sendThrottled(
                "schedule_stale:{$schedule->id}",
                "⚠️ Синхронизация зависла\n\n".
                "Аккаунт: {$domain}\n".
                "Тип: {$schedule->entity_type} (schedule #{$schedule->id})\n".
                "Ожидаемый интервал: {$expectedEvery} мин\n".
                "Последний успешный запуск: {$sinceText}",
                minutes: 360,
            );
        }
    }

    /**
     * amoCRM auto-disables a webhook subscription after enough consecutive
     * delivery failures — and this happened silently for weeks before it was
     * noticed (2026-07-01..2026-08-10 for anyservice), since a disabled
     * webhook just goes quiet rather than erroring anywhere visible. Poll
     * amoCRM's own webhook list so this gets caught the same day instead.
     */
    private function reportDisabledWebhooks(TelegramNotifier $notifier, AmoWebhooksRegistrationService $webhooksService): void
    {
        $accounts = AmoAccount::query()->active()->whereNotNull('webhook_key')->get();

        foreach ($accounts as $account) {
            try {
                $hooks = $webhooksService->list($account);
            } catch (Throwable $exception) {
                Log::warning('Failed to check webhook status.', [
                    'amo_account_id' => $account->id,
                    'exception' => $exception,
                ]);

                continue;
            }

            $ours = collect($hooks)->first(
                fn (array $hook): bool => str_contains((string) ($hook['destination'] ?? ''), $account->webhook_key)
            );

            $domain = $account->base_domain ?? "id {$account->id}";

            if ($ours === null) {
                $notifier->sendThrottled(
                    "webhook_missing:{$account->id}",
                    "🔴 Вебхук не найден в amoCRM\n\n".
                    "Аккаунт: {$domain}\n".
                    'Подписка отсутствует — данные обновляются только по расписанию, без реального времени.',
                    minutes: 360,
                );

                continue;
            }

            if ($ours['disabled'] ?? false) {
                $notifier->sendThrottled(
                    "webhook_disabled:{$account->id}",
                    "🔴 Вебхук отключён в amoCRM\n\n".
                    "Аккаунт: {$domain}\n".
                    'amoCRM отключает подписку после серии сбоев доставки. Переподключить: раздел вебхуков в настройках интеграции amoCRM.',
                    minutes: 360,
                );
            }
        }
    }
}
