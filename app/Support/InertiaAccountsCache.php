<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class InertiaAccountsCache
{
    public static function keyFor(int $userId): string
    {
        return "inertia_accounts_user_{$userId}";
    }

    public static function forgetUser(int $userId): void
    {
        Cache::forget(self::keyFor($userId));
    }

    public static function forgetAll(): void
    {
        // Flush is a blunt tool; use tagged cache or individual keys in production.
        // For a single-tenant app this is acceptable.
        Cache::flush();
    }
}
