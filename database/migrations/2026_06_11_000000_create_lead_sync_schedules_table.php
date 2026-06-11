<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sync_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('amo_pipeline_id');
            $table->string('pipeline_name')->nullable();
            $table->unsignedInteger('interval_minutes');
            $table->unsignedSmallInteger('lookback_days')->default(45);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->string('last_status')->nullable();
            $table->unsignedInteger('last_synced_count')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['amo_account_id', 'amo_pipeline_id']);
            $table->index(['amo_account_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sync_schedules');
    }
};
