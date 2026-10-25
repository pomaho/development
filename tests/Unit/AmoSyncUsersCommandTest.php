<?php

namespace Tests\Unit;

use App\Models\AmoAccount;
use App\Services\Amo\Structure\AmoUsersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AmoSyncUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_failing_account_does_not_stop_the_others(): void
    {
        $broken = AmoAccount::query()->create(['name' => 'Broken', 'base_domain' => 'broken.amocrm.ru', 'is_active' => true]);
        $healthy = AmoAccount::query()->create(['name' => 'Healthy', 'base_domain' => 'healthy.amocrm.ru', 'is_active' => true]);

        $synced = [];
        $usersService = Mockery::mock(AmoUsersService::class);
        $usersService->shouldReceive('syncUsersAndRoles')->twice()->andReturnUsing(function (AmoAccount $account) use ($broken, &$synced) {
            if ($account->is($broken)) {
                throw new RuntimeException('Token expired');
            }
            $synced[] = $account->id;

            return [];
        });
        $this->app->instance(AmoUsersService::class, $usersService);

        $this->artisan('amo:sync-users')->assertFailed();

        $this->assertSame([$healthy->id], $synced);
    }
}
