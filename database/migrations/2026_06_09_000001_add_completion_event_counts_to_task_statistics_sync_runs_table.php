<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_statistics_sync_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('task_statistics_sync_runs', 'completion_events_found')) {
                $table->unsignedInteger('completion_events_found')->default(0)->after('completed_synced');
            }

            if (! Schema::hasColumn('task_statistics_sync_runs', 'completion_events_synced')) {
                $table->unsignedInteger('completion_events_synced')->default(0)->after('completion_events_found');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_statistics_sync_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('task_statistics_sync_runs', 'completion_events_synced')) {
                $table->dropColumn('completion_events_synced');
            }

            if (Schema::hasColumn('task_statistics_sync_runs', 'completion_events_found')) {
                $table->dropColumn('completion_events_found');
            }
        });
    }
};
