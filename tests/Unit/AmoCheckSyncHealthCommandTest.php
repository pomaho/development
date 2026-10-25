<?php

namespace Tests\Unit;

use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Models\LeadSyncSchedule;
use App\Services\Alerts\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AmoCheckSyncHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_when_sync_watermark_is_in_the_future(): void
    {
        $account = $this->account();
        $account->markTaskStatisticsSyncedUntil(now()->endOfDay()->addMinute());

        $alerts = $this->captureAlerts();

        $this->assertContains("sync_watermark_future:{$account->id}", array_keys($alerts));
    }

    public function test_alerts_when_leads_change_but_lead_status_events_do_not_arrive(): void
    {
        $account = $this->accountWithEventSync();
        $this->leadsUpdated($account, 40);
        $this->statusEvents($account, 2);

        $alerts = $this->captureAlerts();

        $this->assertContains("lead_status_events_missing:{$account->id}", array_keys($alerts));
        $this->assertStringContainsString('обновлено сделок 40, событий смены этапа 2', $alerts["lead_status_events_missing:{$account->id}"]);
    }

    public function test_stays_quiet_when_lead_status_events_keep_up_with_lead_updates(): void
    {
        $account = $this->accountWithEventSync();
        $account->markTaskStatisticsSyncedUntil(now()->subMinutes(10));
        $this->leadsUpdated($account, 40);
        $this->statusEvents($account, 20);

        $alerts = $this->captureAlerts();

        $this->assertNotContains("lead_status_events_missing:{$account->id}", array_keys($alerts));
        $this->assertNotContains("sync_watermark_future:{$account->id}", array_keys($alerts));
    }

    /**
     * @return array<string, string> dedup key => message
     */
    private function captureAlerts(): array
    {
        $alerts = [];
        $notifier = Mockery::mock(TelegramNotifier::class);
        $notifier->shouldReceive('sendThrottled')->andReturnUsing(function (string $key, string $message) use (&$alerts): void {
            $alerts[$key] = $message;
        });
        $this->app->instance(TelegramNotifier::class, $notifier);

        $this->artisan('amo:check-sync-health')->assertExitCode(0);

        return $alerts;
    }

    private function account(): AmoAccount
    {
        return AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru', 'is_active' => true]);
    }

    private function accountWithEventSync(): AmoAccount
    {
        $account = $this->account();
        LeadSyncSchedule::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => LeadSyncSchedule::ENTITY_TYPE_TASKS,
            'interval_minutes' => 60,
            'lookback_days' => 2,
            'is_enabled' => true,
        ]);

        return $account;
    }

    private function leadsUpdated(AmoAccount $account, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            CrmEntitySnapshot::query()->create([
                'amo_account_id' => $account->id,
                'entity_type' => 'leads',
                'external_id' => "lead-{$i}",
                'entity_updated_at' => now()->subHours(5),
                'raw' => [],
                'synced_at' => now(),
            ]);
        }
    }

    private function statusEvents(AmoAccount $account, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            CrmEntitySnapshot::query()->create([
                'amo_account_id' => $account->id,
                'entity_type' => 'events',
                'external_id' => "event-{$i}",
                'entity_created_at' => now()->subHours(5),
                'raw' => ['type' => 'lead_status_changed'],
                'synced_at' => now(),
            ]);
        }
    }
}
