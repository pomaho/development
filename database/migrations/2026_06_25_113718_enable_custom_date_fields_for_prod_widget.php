<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $prodWidgetId = DB::table('dashboard_widgets')
            ->where('code', 'task_overdue_dashboard_v2')
            ->value('id');

        if ($prodWidgetId === null) {
            return;
        }

        $installations = DB::table('amo_account_dashboard_widgets')
            ->where('dashboard_widget_id', $prodWidgetId)
            ->get();

        foreach ($installations as $installation) {
            $config = json_decode($installation->config ?? '{}', true) ?: [];
            if (!empty($config['use_custom_date_fields'])) {
                continue;
            }
            $config['use_custom_date_fields'] = true;
            DB::table('amo_account_dashboard_widgets')
                ->where('id', $installation->id)
                ->update(['config' => json_encode($config), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $prodWidgetId = DB::table('dashboard_widgets')
            ->where('code', 'task_overdue_dashboard_v2')
            ->value('id');

        if ($prodWidgetId === null) {
            return;
        }

        $installations = DB::table('amo_account_dashboard_widgets')
            ->where('dashboard_widget_id', $prodWidgetId)
            ->get();

        foreach ($installations as $installation) {
            $config = json_decode($installation->config ?? '{}', true) ?: [];
            unset($config['use_custom_date_fields']);
            DB::table('amo_account_dashboard_widgets')
                ->where('id', $installation->id)
                ->update(['config' => json_encode($config), 'updated_at' => now()]);
        }
    }
};
