<?php

namespace Tests\Feature;

use App\Models\AmoAccount;
use App\Models\AmoCredential;
use App\Models\AmoUsersSnapshot;
use App\Models\ApiRequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndAmoAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_can_create_amo_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/amo-accounts', [
            'name' => 'Client',
            'base_domain' => 'client.amocrm.ru',
            'auth_type' => AmoCredential::AUTH_LONG_LIVED,
            'access_token' => 'abcdef1234567890',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('amo_accounts', ['base_domain' => 'client.amocrm.ru']);
        $this->assertDatabaseHas('amo_credentials', ['auth_type' => AmoCredential::AUTH_LONG_LIVED]);
    }

    public function test_viewer_cannot_create_or_edit_amo_account(): void
    {
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $this->actingAs($viewer)->post('/amo-accounts', [
            'name' => 'Other',
            'base_domain' => 'other.amocrm.ru',
            'auth_type' => AmoCredential::AUTH_LONG_LIVED,
            'access_token' => 'token',
        ])->assertForbidden();

        $this->actingAs($viewer)->put("/amo-accounts/{$account->id}", [
            'name' => 'Changed',
            'base_domain' => 'client.amocrm.ru',
            'auth_type' => AmoCredential::AUTH_LONG_LIVED,
            'is_active' => '1',
        ])->assertForbidden();
    }

    public function test_admin_can_edit_amo_account(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $account->credentials()->create(['auth_type' => AmoCredential::AUTH_LONG_LIVED, 'access_token' => 'abcdef1234567890']);

        $this->actingAs($admin)->put("/amo-accounts/{$account->id}", [
            'name' => 'Changed',
            'base_domain' => 'client.amocrm.ru',
            'auth_type' => AmoCredential::AUTH_LONG_LIVED,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('amo_accounts', ['id' => $account->id, 'name' => 'Changed']);
    }

    public function test_secrets_are_masked_and_not_rendered(): void
    {
        $admin = User::factory()->admin()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);
        $account->credentials()->create(['auth_type' => AmoCredential::AUTH_LONG_LIVED, 'access_token' => 'abcdef1234567890']);

        $this->actingAs($admin)->get("/amo-accounts/{$account->id}/edit")
            ->assertSee('abcdef******7890')
            ->assertDontSee('value="abcdef1234567890"', false);
    }

    public function test_secrets_are_not_stored_in_api_logs(): void
    {
        ApiRequestLog::query()->create([
            'method' => 'POST',
            'url' => 'https://client.amocrm.ru/api/v4/test',
            'request_payload' => ['access_token' => '[redacted]', 'client_secret' => '[redacted]'],
        ]);

        $this->assertDatabaseMissing('api_request_logs', ['request_payload' => json_encode(['access_token' => 'secret'])]);
    }

    public function test_account_dashboard_uses_selected_client_context(): void
    {
        $viewer = User::factory()->create();
        $first = AmoAccount::query()->create(['name' => 'First', 'base_domain' => 'first.amocrm.ru']);
        $second = AmoAccount::query()->create(['name' => 'Second', 'base_domain' => 'second.amocrm.ru']);

        AmoUsersSnapshot::query()->create([
            'amo_account_id' => $first->id,
            'amo_user_id' => 1,
            'name' => 'First Admin',
            'rights' => ['is_admin' => true],
            'is_admin' => true,
            'is_active' => true,
            'raw' => [],
            'synced_at' => now(),
        ]);
        AmoUsersSnapshot::query()->create([
            'amo_account_id' => $second->id,
            'amo_user_id' => 2,
            'name' => 'Second User',
            'rights' => [],
            'is_admin' => false,
            'is_active' => true,
            'raw' => [],
            'synced_at' => now(),
        ]);

        $this->actingAs($viewer)->get("/amo-accounts/{$first->id}/dashboard")
            ->assertOk()
            ->assertSee('Dashboard: First')
            ->assertSee('first.amocrm.ru')
            ->assertSee('Пользователи')
            ->assertSee('Администраторы');
    }

    public function test_admin_can_open_pipeline_create_form_and_viewer_cannot_create_pipeline(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/pipelines/create")
            ->assertOk()
            ->assertSee('Создать воронку');

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/pipelines", [
                'name' => 'Pipeline',
                'sort' => 20,
                'is_unsorted_on' => '1',
                'statuses' => [
                    ['name' => 'Первичный контакт', 'sort' => 10, 'color' => '#99ccff'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_open_crm_audit_and_viewer_cannot_run_it(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $account = AmoAccount::query()->create(['name' => 'Client', 'base_domain' => 'client.amocrm.ru']);

        $this->actingAs($admin)
            ->get("/amo-accounts/{$account->id}/crm-audit")
            ->assertOk()
            ->assertSee('CRM-аудит');

        $this->actingAs($viewer)
            ->post("/amo-accounts/{$account->id}/crm-audit/sync", [
                'from' => '2026-01-01',
                'to' => '2026-05-05',
            ])
            ->assertForbidden();
    }
}
