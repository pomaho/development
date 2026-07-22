<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sync_schedules', function (Blueprint $table): void {
            // When true, the recurring sync filters leads by amoCRM's updated_at instead of
            // created_at, so edits to existing (already-synced) leads are picked up too, not
            // just newly created ones. See AmoTaskStatisticsService "Менеджер не указан" bucket.
            $table->boolean('use_updated_at')->default(false)->after('lookback_days');
        });
    }

    public function down(): void
    {
        Schema::table('lead_sync_schedules', function (Blueprint $table): void {
            $table->dropColumn('use_updated_at');
        });
    }
};
