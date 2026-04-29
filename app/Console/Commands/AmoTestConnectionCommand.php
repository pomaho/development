<?php

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Services\Amo\AmoFallbackHttpClient;
use Illuminate\Console\Command;

class AmoTestConnectionCommand extends Command
{
    protected $signature = 'amo:test-connection {accountId}';
    protected $description = 'Run GET /api/v4/account against an amoCRM account.';

    public function handle(AmoFallbackHttpClient $http): int
    {
        $account = AmoAccount::query()->findOrFail((int) $this->argument('accountId'));
        $payload = $http->get($account, '/api/v4/account');

        $account->forceFill([
            'account_id' => $payload['id'] ?? $account->account_id,
            'auth_status' => 'ok',
        ])->save();

        $this->components->info('Connection OK.');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
