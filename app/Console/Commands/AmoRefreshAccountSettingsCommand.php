<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Services\Amo\Accounts\AmoAccountProfileService;
use Illuminate\Console\Command;
use Throwable;

class AmoRefreshAccountSettingsCommand extends Command
{
    protected $signature = 'amo:refresh-account-settings
                            {--account= : Specific account ID (omit to refresh all)}';

    protected $description = 'Pull /api/v4/account from amoCRM and re-save settings (timezone, currency, etc.)';

    public function handle(AmoAccountProfileService $profileService): int
    {
        $query = AmoAccount::query();

        if ($id = $this->option('account')) {
            $query->where('id', (int) $id);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->components->warn('No accounts found.');
            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $this->components->task(
                "Account #{$account->id} {$account->name} ({$account->base_domain})",
                function () use ($account, $profileService): bool {
                    try {
                        $updated = $profileService->refreshAccountSettings($account);
                        $tz = $updated->timezone();
                        $this->line("  timezone: <comment>{$tz}</comment>");
                        return true;
                    } catch (Throwable $e) {
                        $this->line("  <error>{$e->getMessage()}</error>");
                        return false;
                    }
                }
            );
        }

        return self::SUCCESS;
    }
}
