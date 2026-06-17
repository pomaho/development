<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sync_schedules', function (Blueprint $table): void {
            $table->string('entity_type')->default('leads')->after('amo_account_id');
            $table->unsignedBigInteger('amo_pipeline_id')->nullable()->change();

            $table->dropUnique(['amo_account_id', 'amo_pipeline_id']);
            $table->unique(['amo_account_id', 'entity_type', 'amo_pipeline_id'], 'lead_sync_schedules_account_entity_pipeline_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lead_sync_schedules', function (Blueprint $table): void {
            $table->dropUnique('lead_sync_schedules_account_entity_pipeline_unique');
            $table->dropColumn('entity_type');
            $table->unsignedBigInteger('amo_pipeline_id')->nullable(false)->change();
            $table->unique(['amo_account_id', 'amo_pipeline_id']);
        });
    }
};
