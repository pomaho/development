<?php

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Models\AmoCredential;
use App\Services\Amo\AmoTokenManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AmoRefreshTokensCommand extends Command
{
    protected $signature = 'amo:refresh-tokens';
    protected $description = 'Refresh expiring amoCRM OAuth tokens.';

    public function handle(AmoTokenManager $tokenManager): int
    {
        AmoAccount::query()
            ->whereHas('credentials', fn ($query) => $query->where('auth_type', AmoCredential::AUTH_OAUTH))
            ->with('credentials')
            ->chunkById(100, function ($accounts) use ($tokenManager): void {
                foreach ($accounts as $account) {
                    try {
                        $tokenManager->accessTokenFor($account);
                        $this->components->info("Token OK: {$account->base_domain}");
                    } catch (Throwable $exception) {
                        Log::warning('amoCRM token refresh command failed', [
                            'amo_account_id' => $account->id,
                            'error' => $exception->getMessage(),
                        ]);
                        $this->components->warn("Token refresh failed: {$account->base_domain}");
                    }
                }
            });

        return self::SUCCESS;
    }
}
