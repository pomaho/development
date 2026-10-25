<?php

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Services\Amo\Structure\AmoUsersService;
use Illuminate\Console\Command;
use Throwable;

class AmoSyncUsersCommand extends Command
{
    protected $signature = 'amo:sync-users {accountId?}';
    protected $description = 'Sync amoCRM users and roles for one or all active accounts.';

    public function handle(AmoUsersService $usersService): int
    {
        $query = AmoAccount::query()->active();

        if ($this->argument('accountId')) {
            $query->whereKey((int) $this->argument('accountId'));
        }

        $accounts = $query->get();
        $failed = false;

        // One account failing (e.g. an expired token) must not stop the rest from
        // syncing — this runs unattended on a schedule.
        foreach ($accounts as $account) {
            try {
                $this->components->task("Sync {$account->base_domain}", fn () => $usersService->syncUsersAndRoles($account));
            } catch (Throwable $exception) {
                $failed = true;
                $this->components->error("{$account->base_domain}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
