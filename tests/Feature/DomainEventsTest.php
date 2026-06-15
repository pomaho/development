<?php

namespace Tests\Feature;

use App\Events\AmoAccountSynced;
use App\Events\AmoAccountSyncQueued;
use App\Models\AmoAccount;
use App\Models\User;
use App\Support\InertiaAccountsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function makeAccount(int $ownerId): AmoAccount
    {
        return AmoAccount::query()->create([
            'name' => 'Test',
            'base_domain' => 'test.amocrm.ru',
            'owner_user_id' => $ownerId,
        ]);
    }

    public function test_sync_action_dispatches_sync_queued_event(): void
    {
        Event::fake([AmoAccountSyncQueued::class]);

        $user = User::factory()->admin()->create();
        $account = $this->makeAccount($user->id);

        $this->actingAs($user)->post(route('amo-accounts.sync', $account));

        Event::assertDispatched(AmoAccountSyncQueued::class, fn ($e) => $e->account->id === $account->id);
    }

    public function test_sync_queued_event_invalidates_inertia_accounts_cache(): void
    {
        $user = User::factory()->admin()->create();
        $account = $this->makeAccount($user->id);

        $cacheKey = InertiaAccountsCache::keyFor($user->id);
        Cache::put($cacheKey, ['cached' => true], 300);
        $this->assertTrue(Cache::has($cacheKey));

        AmoAccountSyncQueued::dispatch($account);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_account_synced_event_invalidates_inertia_accounts_cache(): void
    {
        $user = User::factory()->admin()->create();
        $account = $this->makeAccount($user->id);

        $cacheKey = InertiaAccountsCache::keyFor($user->id);
        Cache::put($cacheKey, ['cached' => true], 300);
        $this->assertTrue(Cache::has($cacheKey));

        AmoAccountSynced::dispatch($account);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_event_does_not_fail_for_account_without_owner(): void
    {
        $account = AmoAccount::query()->create([
            'name' => 'Orphan',
            'base_domain' => 'orphan.amocrm.ru',
            'owner_user_id' => null,
        ]);

        $this->expectNotToPerformAssertions();

        AmoAccountSynced::dispatch($account);
        AmoAccountSyncQueued::dispatch($account);
    }

    public function test_cache_key_format_is_stable(): void
    {
        $this->assertSame('inertia_accounts_user_42', InertiaAccountsCache::keyFor(42));
    }
}
