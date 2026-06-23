<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dashboard_widgets')->insertOrIgnore([
            'code' => 'task_overdue_dashboard_v2_dev',
            'name' => 'Рекрутинг DEV (тестовая версия)',
            'description' => 'Dev-версия V2 виджета для тестирования изменений перед накаткой на прод.',
            'component_key' => 'amo_iframe_task_overdue_dashboard_v2_dev',
            'sort_order' => 72,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $devWidgetId = DB::table('dashboard_widgets')->where('code', 'task_overdue_dashboard_v2_dev')->value('id');

        // For each account that has v2 installed, create a dev installation with the same config + use_custom_date_fields
        $prodWidgetId = DB::table('dashboard_widgets')->where('code', 'task_overdue_dashboard_v2')->value('id');
        if ($prodWidgetId === null) {
            return;
        }

        $prodInstallations = DB::table('amo_account_dashboard_widgets')
            ->where('dashboard_widget_id', $prodWidgetId)
            ->where('is_enabled', true)
            ->get();

        foreach ($prodInstallations as $installation) {
            $alreadyExists = DB::table('amo_account_dashboard_widgets')
                ->where('amo_account_id', $installation->amo_account_id)
                ->where('dashboard_widget_id', $devWidgetId)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $config = json_decode($installation->config ?? '{}', true) ?: [];
            $config['use_custom_date_fields'] = true;

            DB::table('amo_account_dashboard_widgets')->insert([
                'amo_account_id' => $installation->amo_account_id,
                'dashboard_widget_id' => $devWidgetId,
                'public_key' => Str::random(48),
                'is_enabled' => true,
                'config' => json_encode($config),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $devWidgetId = DB::table('dashboard_widgets')->where('code', 'task_overdue_dashboard_v2_dev')->value('id');
        if ($devWidgetId !== null) {
            DB::table('amo_account_dashboard_widgets')->where('dashboard_widget_id', $devWidgetId)->delete();
        }
        DB::table('dashboard_widgets')->where('code', 'task_overdue_dashboard_v2_dev')->delete();
    }
};
