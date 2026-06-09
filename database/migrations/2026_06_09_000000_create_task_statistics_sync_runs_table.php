<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_statistics_sync_runs')) {
            return;
        }

        Schema::create('task_statistics_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('period_from')->nullable();
            $table->timestamp('period_to')->nullable();
            $table->unsignedInteger('completed_found')->default(0);
            $table->unsignedInteger('completed_synced')->default(0);
            $table->unsignedInteger('open_found')->default(0);
            $table->unsignedInteger('open_synced')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['amo_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_statistics_sync_runs');
    }
};
