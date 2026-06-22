<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dashboard_widgets')->insertOrIgnore([
            'code' => 'manager_topup_dashboard',
            'name' => 'Доплаты по менеджерам',
            'component_key' => 'Widgets/Amo/ManagerTopupDashboard',
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $widgetId = DB::table('dashboard_widgets')->where('code', 'manager_topup_dashboard')->value('id');

        // Link to account ID 3 — only if it exists (skip in local dev where DB is empty)
        $accountExists = DB::table('amo_accounts')->where('id', 3)->exists();
        if ($accountExists) {
            $exists = DB::table('amo_account_dashboard_widgets')
                ->where('amo_account_id', 3)
                ->where('dashboard_widget_id', $widgetId)
                ->exists();

            if (!$exists) {
                DB::table('amo_account_dashboard_widgets')->insert([
                    'amo_account_id' => 3,
                    'dashboard_widget_id' => $widgetId,
                    'public_key' => Str::random(48),
                    'is_enabled' => true,
                    'config' => json_encode([
                        'pipeline_id' => 10904262,
                        'pipeline_name' => 'Массовый подбор',
                        'prepayment_field_id' => 845975,
                        'manager_field_id' => 845835,
                        'topup_date_field_id' => 845843,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('code', 'manager_topup_dashboard')->delete();
    }
};
