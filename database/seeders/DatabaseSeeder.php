<?php

namespace Database\Seeders;

use App\Models\DashboardWidget;
use App\Models\IntegrationModule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'role' => User::ROLE_ADMIN,
            ]
        );

        IntegrationModule::query()->updateOrCreate(
            ['code' => 'users_audit'],
            [
                'name' => 'Users Audit',
                'description' => 'Синхронизация пользователей, ролей и прав amoCRM.',
                'is_enabled' => true,
                'config' => [],
            ]
        );

        foreach ($this->widgets() as $widget) {
            DashboardWidget::query()->updateOrCreate(['code' => $widget['code']], $widget);
        }
    }

    private function widgets(): array
    {
        return [
            ['code' => 'amo_accounts_count', 'name' => 'Amo accounts count', 'component_key' => 'metric', 'sort_order' => 10, 'is_enabled' => true],
            ['code' => 'active_amo_accounts_count', 'name' => 'Active amo accounts count', 'component_key' => 'metric', 'sort_order' => 20, 'is_enabled' => true],
            ['code' => 'users_count', 'name' => 'Users count', 'component_key' => 'metric', 'sort_order' => 30, 'is_enabled' => true],
            ['code' => 'admins_count', 'name' => 'Admins count', 'component_key' => 'metric', 'sort_order' => 40, 'is_enabled' => true],
            ['code' => 'last_sync_status', 'name' => 'Last sync status', 'component_key' => 'metric', 'sort_order' => 50, 'is_enabled' => true],
            ['code' => 'recent_api_errors', 'name' => 'Recent API errors', 'component_key' => 'table', 'sort_order' => 60, 'is_enabled' => true],
        ];
    }
}
