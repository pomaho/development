<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responsibility_redistribution_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('source_user_id');
            $table->json('target_user_ids');
            $table->string('status')->default('pending');
            $table->json('preview')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['amo_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsibility_redistribution_runs');
    }
};
