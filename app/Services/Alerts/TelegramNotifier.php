<?php

namespace App\Services\Alerts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramNotifier
{
    public function send(string $message): bool
    {
        $token = config('alerts.telegram.bot_token');
        $chatId = config('alerts.telegram.chat_id');

        if (! $token || ! $chatId) {
            return false;
        }

        try {
            $response = Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => mb_substr($message, 0, 4000),
                'disable_web_page_preview' => true,
            ]);

            if ($response->failed()) {
                Log::warning('Telegram alert rejected by API', ['status' => $response->status()]);

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('Failed to send Telegram alert: '.str_replace($token, '***', $exception->getMessage()));

            return false;
        }
    }

    /**
     * Send at most once per $dedupKey within the throttle window, to avoid
     * flooding the chat when the same failure repeats (e.g. a crash loop).
     * The dedup key is only set once the message is confirmed sent, so a
     * failed delivery doesn't suppress the next retry.
     */
    public function sendThrottled(string $dedupKey, string $message, ?int $minutes = null): void
    {
        $cacheKey = 'alerts:telegram:'.md5($dedupKey);
        $minutes ??= (int) config('alerts.throttle_minutes', 15);

        if (Cache::has($cacheKey)) {
            return;
        }

        if ($this->send($message)) {
            Cache::put($cacheKey, true, now()->addMinutes($minutes));
        }
    }
}
