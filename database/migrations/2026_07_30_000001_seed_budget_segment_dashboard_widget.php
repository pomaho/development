<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dashboard_widgets')->insertOrIgnore([
            'code' => 'budget_segment_dashboard',
            'name' => 'Сегментация по бюджетам',
            'component_key' => 'Widgets/Amo/Clients/Eurohome/BudgetSegmentDashboard',
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $widgetId = DB::table('dashboard_widgets')->where('code', 'budget_segment_dashboard')->value('id');

        // Link to account ID 3 (Eurohome) — only if it exists (skip in local dev where DB is empty)
        $accountExists = DB::table('amo_accounts')->where('id', 3)->exists();
        if ($accountExists) {
            $exists = DB::table('amo_account_dashboard_widgets')
                ->where('amo_account_id', 3)
                ->where('dashboard_widget_id', $widgetId)
                ->exists();

            if (!$exists) {
                // Segments derived from the live active-deal price distribution at seed time
                // (246 active deals: min 8 650, p25 380 000, median 947 162, p90 2 900 000, max 12 500 000)
                // — chosen to split roughly evenly (52 / 49 / 70 / 53 / 22 deals).
                DB::table('amo_account_dashboard_widgets')->insert([
                    'amo_account_id' => 3,
                    'dashboard_widget_id' => $widgetId,
                    'public_key' => Str::random(48),
                    'is_enabled' => true,
                    'config' => json_encode([
                        'pipeline_id' => 10904262,
                        'pipeline_name' => 'Массовый подбор',
                        'budget_segments' => [
                            ['label' => 'до 300 000', 'min' => 0, 'max' => 300000],
                            ['label' => '300 000 – 700 000', 'min' => 300000, 'max' => 700000],
                            ['label' => '700 000 – 1 500 000', 'min' => 700000, 'max' => 1500000],
                            ['label' => '1 500 000 – 3 000 000', 'min' => 1500000, 'max' => 3000000],
                            ['label' => 'свыше 3 000 000', 'min' => 3000000, 'max' => null],
                        ],
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('code', 'budget_segment_dashboard')->delete();
    }
};
