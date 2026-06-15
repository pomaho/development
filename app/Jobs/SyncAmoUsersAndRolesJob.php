<?php

namespace App\Jobs;

use App\Events\AmoAccountSynced;
use App\Models\AmoAccount;
use App\Models\ApiRequestLog;
use App\Services\Amo\Structure\AmoUsersService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAmoUsersAndRolesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly int $amoAccountId)
    {
    }

    public function handle(AmoUsersService $usersService): void
    {
        $account = AmoAccount::query()->findOrFail($this->amoAccountId);

        try {
            $usersService->syncUsersAndRoles($account);
            Log::info('amoCRM users and roles synced', ['amo_account_id' => $account->id]);
            AmoAccountSynced::dispatch($account);
        } catch (Throwable $exception) {
            ApiRequestLog::query()->create([
                'amo_account_id' => $account->id,
                'method' => 'JOB',
                'url' => 'amo:sync-users',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
