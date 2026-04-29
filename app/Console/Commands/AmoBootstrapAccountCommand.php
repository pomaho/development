<?php

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Models\AmoCredential;
use Illuminate\Console\Command;

class AmoBootstrapAccountCommand extends Command
{
    protected $signature = 'amo:bootstrap-account';
    protected $description = 'Create the first local amoCRM account from env values.';

    public function handle(): int
    {
        if (! config('amo.bootstrap.enabled')) {
            $this->components->info('AMO_BOOTSTRAP_ENABLED is false.');
            return self::SUCCESS;
        }

        $domain = $this->normalizeDomain((string) config('amo.bootstrap.base_domain'));
        $token = (string) config('amo.bootstrap.access_token');

        if (! $domain || ! $token) {
            $this->components->warn('AMO_BOOTSTRAP_BASE_DOMAIN and AMO_BOOTSTRAP_ACCESS_TOKEN are required.');
            return self::SUCCESS;
        }

        $account = AmoAccount::query()->firstOrCreate(
            ['base_domain' => $domain],
            [
                'name' => config('amo.bootstrap.name'),
                'is_active' => true,
                'auth_status' => 'not_checked',
            ]
        );

        $account->credentials()->updateOrCreate(
            ['amo_account_id' => $account->id],
            [
                'auth_type' => AmoCredential::AUTH_LONG_LIVED,
                'access_token' => $token,
            ]
        );

        $this->components->info("amoCRM account ready: {$account->id} {$account->base_domain}");

        return self::SUCCESS;
    }

    private function normalizeDomain(string $domain): string
    {
        return trim(preg_replace('#^https?://#', '', $domain), '/');
    }
}
