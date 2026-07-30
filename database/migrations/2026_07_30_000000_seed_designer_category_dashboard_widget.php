<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dashboard_widgets')->insertOrIgnore([
            'code' => 'designer_category_dashboard',
            'name' => 'Сделки по категориям дизайнеров',
            'component_key' => 'Widgets/Amo/Clients/Eurohome/DesignerCategoryDashboard',
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $widgetId = DB::table('dashboard_widgets')->where('code', 'designer_category_dashboard')->value('id');

        // Link to account ID 3 (Eurohome) — only if it exists (skip in local dev where DB is empty)
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
                        'category_field_id' => 845859,
                        'category_field_name' => 'Категория ABCDE',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('code', 'designer_category_dashboard')->delete();
    }
};
