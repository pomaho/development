<?php

namespace App\Services\Alerts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramNotifier
{
    public function send(string $message): void
    {
        $token = config('alerts.telegram.bot_token');
        $chatId = config('alerts.telegram.chat_id');

        if (! $token || ! $chatId) {
            return;
        }

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => mb_substr($message, 0, 4000),
                'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to send Telegram alert: '.$exception->getMessage());
        }
    }

    /**
     * Send at most once per $dedupKey within the throttle window, to avoid
     * flooding the chat when the same failure repeats (e.g. a crash loop).
     */
    public function sendThrottled(string $dedupKey, string $message, ?int $minutes = null): void
    {
        $cacheKey = 'alerts:telegram:'.md5($dedupKey);
        $minutes ??= (int) config('alerts.throttle_minutes', 15);

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addMinutes($minutes));
        $this->send($message);
    }
}
