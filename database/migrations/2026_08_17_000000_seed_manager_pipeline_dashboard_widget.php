<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dashboard_widgets')->insertOrIgnore([
            'code' => 'manager_pipeline_dashboard',
            'name' => 'Менеджеры подбор',
            'description' => 'Конверсия и выход на смену по воронке "Менеджеры подбор", в разбивке по менеджерам.',
            'component_key' => 'Widgets/Amo/ManagerPipelineDashboard',
            'sort_order' => 73,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $widgetId = DB::table('dashboard_widgets')->where('code', 'manager_pipeline_dashboard')->value('id');

        // Link to account ID 2 (anyservice) — only account with this pipeline.
        $accountExists = DB::table('amo_accounts')->where('id', 2)->exists();
        if ($accountExists) {
            $exists = DB::table('amo_account_dashboard_widgets')
                ->where('amo_account_id', 2)
                ->where('dashboard_widget_id', $widgetId)
                ->exists();

            if (!$exists) {
                DB::table('amo_account_dashboard_widgets')->insert([
                    'amo_account_id' => 2,
                    'dashboard_widget_id' => $widgetId,
                    'public_key' => Str::random(48),
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $widgetId = DB::table('dashboard_widgets')->where('code', 'manager_pipeline_dashboard')->value('id');
        if ($widgetId !== null) {
            DB::table('amo_account_dashboard_widgets')->where('dashboard_widget_id', $widgetId)->delete();
        }
        DB::table('dashboard_widgets')->where('code', 'manager_pipeline_dashboard')->delete();
    }
};
