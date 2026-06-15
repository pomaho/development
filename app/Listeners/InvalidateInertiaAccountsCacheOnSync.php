<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AmoAccountSynced;
use App\Events\AmoAccountSyncQueued;
use App\Support\InertiaAccountsCache;

final class InvalidateInertiaAccountsCacheOnSync
{
    public function handleSynced(AmoAccountSynced $event): void
    {
        $ownerId = $event->account->owner_user_id;

        if ($ownerId !== null) {
            InertiaAccountsCache::forgetUser((int) $ownerId);
        }
    }

    public function handleSyncQueued(AmoAccountSyncQueued $event): void
    {
        $ownerId = $event->account->owner_user_id;

        if ($ownerId !== null) {
            InertiaAccountsCache::forgetUser((int) $ownerId);
        }
    }
}
