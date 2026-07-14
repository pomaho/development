<?php

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

        $notifier->send('✅ Тестовое уведомление: алерты настроены и работают.');
        $this->info('Отправлено.');

        return self::SUCCESS;
    }
}
