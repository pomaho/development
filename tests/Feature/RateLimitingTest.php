<?php

namespace Tests\Feature;

use App\Models\AmoAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function makeAccount(array $attributes = []): AmoAccount
    {
        return AmoAccount::query()->create(array_merge(
            ['name' => 'Test', 'base_domain' => 'test.amocrm.ru', 'is_active' => true],
            $attributes
        ));
    }

    // Laravel ThrottleRequests builds key as md5($limiterName . $limit->key) when hashing is enabled (default)
    private function exhaustLimit(string $limiterName, string $byKey, int $times): void
    {
        $cacheKey = md5($limiterName . $byKey);
        for ($i = 0; $i < $times; $i++) {
            RateLimiter::hit($cacheKey, 60);
        }
    }

    public function test_webhook_has_rate_limit_header(): void
    {
        $account = $this->makeAccount();

        $response = $this->postJson(route('webhooks.amo', $account->webhook_key), []);

        $this->assertNotEquals(429, $response->status());
        $response->assertHeader('X-RateLimit-Limit', 60);
    }

    public function test_webhook_returns_429_when_limit_exhausted(): void
    {
        $account = $this->makeAccount();
        $this->exhaustLimit('webhook', (string) $account->webhook_key, 60);

        $response = $this->postJson(route('webhooks.amo', $account->webhook_key), []);

        $response->assertStatus(429);
    }

    public function test_sync_has_rate_limit_header(): void
    {
        $user = User::factory()->admin()->create();
        $account = $this->makeAccount();

        $response = $this->actingAs($user)
            ->post(route('amo-accounts.sync', $account));

        $this->assertNotEquals(429, $response->status());
        $response->assertHeader('X-RateLimit-Limit', 5);
    }

    public function test_sync_returns_429_when_limit_exhausted(): void
    {
        $user = User::factory()->admin()->create();
        $account = $this->makeAccount();
        $this->exhaustLimit('amo-sync', (string) $user->id, 5);

        $response = $this->actingAs($user)
            ->post(route('amo-accounts.sync', $account));

        $response->assertStatus(429);
    }

    public function test_crm_audit_sync_has_rate_limit_header(): void
    {
        $user = User::factory()->admin()->create();
        $account = $this->makeAccount();

        $response = $this->actingAs($user)
            ->post(route('amo-accounts.crm-audit.sync', $account));

        $this->assertNotEquals(429, $response->status());
        $response->assertHeader('X-RateLimit-Limit', 5);
    }

    public function test_rate_limit_is_per_user_not_global(): void
    {
        $user1 = User::factory()->admin()->create();
        $user2 = User::factory()->admin()->create();
        $account = $this->makeAccount();
        $this->exhaustLimit('amo-sync', (string) $user1->id, 5);

        $response = $this->actingAs($user2)
            ->post(route('amo-accounts.sync', $account));

        $this->assertNotEquals(429, $response->status());
    }
}
