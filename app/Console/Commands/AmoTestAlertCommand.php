<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Alerts\TelegramNotifier;
use Illuminate\Console\Command;

class AmoTestAlertCommand extends Command
{
    protected $signature = 'amo:test-alert';

    protected $description = 'Send a test message to the configured Telegram alert chat.';

    public function handle(TelegramNotifier $notifier): int
    {
        if (! config('alerts.telegram.bot_token') || ! config('alerts.telegram.chat_id')) {
            $this->error('ALERTS_TELEGRAM_BOT_TOKEN / ALERTS_TELEGRAM_CHAT_ID не заданы.');

            return self::FAILURE;
        }

        if (! $notifier->send('✅ Тестовое уведомление: алерты настроены и работают.')) {
            $this->error('Telegram API отклонил сообщение или недоступен. Проверьте логи.');

            return self::FAILURE;
        }

        $this->info('Отправлено.');

        return self::SUCCESS;
    }
}
