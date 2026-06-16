<?php

namespace Tests\Feature;

use App\Models\AmoAccount;
use App\Models\ApiRequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneApiLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(int $daysAgo): ApiRequestLog
    {
        $account = AmoAccount::query()->firstOrCreate(
            ['base_domain' => 'test.amocrm.ru'],
            ['name' => 'Test'],
        );

        $log = ApiRequestLog::query()->create([
            'amo_account_id' => $account->id,
            'method' => 'GET',
            'url' => 'https://example.amocrm.ru/api/v4/leads',
        ]);

        $log->created_at = now()->subDays($daysAgo);
        $log->save();

        return $log;
    }

    public function test_deletes_logs_older_than_retention_window(): void
    {
        $old = $this->makeLog(31);
        $recent = $this->makeLog(1);

        $this->artisan('amo:prune-api-logs', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutputToContain('Pruned 1 API log');

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_does_not_delete_logs_within_retention_window(): void
    {
        $log = $this->makeLog(5);

        $this->artisan('amo:prune-api-logs', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutputToContain('Pruned 0 API log');

        $this->assertModelExists($log);
    }

    public function test_custom_days_option_is_respected(): void
    {
        $old = $this->makeLog(8);
        $recent = $this->makeLog(3);

        $this->artisan('amo:prune-api-logs', ['--days' => 7])
            ->assertSuccessful()
            ->expectsOutputToContain('Pruned 1 API log');

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
