<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sync_schedules', function (Blueprint $table): void {
            // Tracks the point through which the updated_at-based leads sync has
            // successfully covered, so each run pulls forward from here instead of
            // a rolling "now - lookback_days" window. A rolling window permanently
            // loses any lead whose last real amoCRM update falls outside it once
            // the window has moved on (e.g. after a sync outage, or — as happened
            // 2026-07-23..08-10 — a switch from created_at-based to updated_at-based
            // sync). The watermark makes coverage resume where it left off instead.
            $table->timestamp('synced_watermark_at')->nullable()->after('use_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('lead_sync_schedules', function (Blueprint $table): void {
            $table->dropColumn('synced_watermark_at');
        });
    }
};
