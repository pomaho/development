<?php

namespace Tests\Feature;

use App\Models\AmoAccount;
use App\Models\AmoWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneWebhookEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(string $status, int $daysAgo): AmoWebhookEvent
    {
        $account = AmoAccount::query()->firstOrCreate(
            ['base_domain' => 'test.amocrm.ru'],
            ['name' => 'Test'],
        );

        return AmoWebhookEvent::query()->create([
            'amo_account_id' => $account->id,
            'event_type' => 'leads.update',
            'entity_type' => 'leads',
            'entity_id' => '100',
            'payload' => ['id' => 100],
            'status' => $status,
            'received_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_deletes_old_processed_and_skipped_events_only(): void
    {
        $oldProcessed = $this->makeEvent(AmoWebhookEvent::STATUS_PROCESSED, 31);
        $oldSkipped = $this->makeEvent(AmoWebhookEvent::STATUS_SKIPPED, 40);
        $oldFailed = $this->makeEvent(AmoWebhookEvent::STATUS_FAILED, 40);
        $oldPending = $this->makeEvent(AmoWebhookEvent::STATUS_PENDING, 40);
        $recentProcessed = $this->makeEvent(AmoWebhookEvent::STATUS_PROCESSED, 5);

        $this->artisan('amo:prune-webhook-events')
            ->assertSuccessful()
            ->expectsOutputToContain('Pruned 2 webhook event');

        $this->assertModelMissing($oldProcessed);
        $this->assertModelMissing($oldSkipped);
        $this->assertModelExists($oldFailed);
        $this->assertModelExists($oldPending);
        $this->assertModelExists($recentProcessed);
    }

    public function test_custom_days_option_is_respected(): void
    {
        $old = $this->makeEvent(AmoWebhookEvent::STATUS_PROCESSED, 8);
        $recent = $this->makeEvent(AmoWebhookEvent::STATUS_PROCESSED, 3);

        $this->artisan('amo:prune-webhook-events', ['--days' => 7])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
