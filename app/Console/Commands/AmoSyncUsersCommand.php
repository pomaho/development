<?php

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Services\Amo\Structure\AmoUsersService;
use Illuminate\Console\Command;

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

        foreach ($accounts as $account) {
            $this->components->task("Sync {$account->base_domain}", fn () => $usersService->syncUsersAndRoles($account));
        }

        return self::SUCCESS;
    }
}
