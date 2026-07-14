<?php

return [
    'telegram' => [
        'bot_token' => env('ALERTS_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('ALERTS_TELEGRAM_CHAT_ID'),
    ],

    // Minutes to suppress duplicate alerts with the same dedup key.
    'throttle_minutes' => (int) env('ALERTS_THROTTLE_MINUTES', 15),

    // How many times over its configured interval a sync schedule may be
    // overdue before it's considered stuck and reported.
    'stale_schedule_multiplier' => (int) env('ALERTS_STALE_SCHEDULE_MULTIPLIER', 3),
];
