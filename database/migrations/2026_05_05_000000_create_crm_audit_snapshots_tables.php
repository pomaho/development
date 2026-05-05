<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_pipelines_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('amo_pipeline_id');
            $table->string('name');
            $table->integer('sort')->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_unsorted_on')->default(false);
            $table->boolean('is_archive')->default(false);
            $table->json('raw');
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->unique(['amo_account_id', 'amo_pipeline_id']);
        });

        Schema::create('crm_pipeline_status_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('amo_pipeline_id')->index();
            $table->unsignedBigInteger('amo_status_id');
            $table->string('name');
            $table->integer('sort')->nullable();
            $table->string('color')->nullable();
            $table->string('type')->nullable();
            $table->json('raw');
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->unique(['amo_account_id', 'amo_pipeline_id', 'amo_status_id'], 'crm_status_unique');
        });

        Schema::create('crm_custom_fields_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->string('entity_type')->index();
            $table->unsignedBigInteger('amo_field_id');
            $table->string('name');
            $table->string('field_type')->nullable();
            $table->string('code')->nullable();
            $table->string('group_id')->nullable();
            $table->integer('sort')->nullable();
            $table->boolean('is_required')->nullable();
            $table->boolean('is_api_only')->nullable();
            $table->json('enums')->nullable();
            $table->json('required_statuses')->nullable();
            $table->json('raw');
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->unique(['amo_account_id', 'entity_type', 'amo_field_id'], 'crm_field_unique');
        });

        Schema::create('crm_entity_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->string('entity_type')->index();
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('pipeline_id')->nullable()->index();
            $table->unsignedBigInteger('status_id')->nullable()->index();
            $table->unsignedBigInteger('responsible_user_id')->nullable()->index();
            $table->timestamp('entity_created_at')->nullable();
            $table->timestamp('entity_updated_at')->nullable();
            $table->timestamp('entity_closed_at')->nullable();
            $table->json('custom_fields_values')->nullable();
            $table->json('embedded')->nullable();
            $table->json('raw');
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->unique(['amo_account_id', 'entity_type', 'external_id'], 'crm_entity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_entity_snapshots');
        Schema::dropIfExists('crm_custom_fields_snapshots');
        Schema::dropIfExists('crm_pipeline_status_snapshots');
        Schema::dropIfExists('crm_pipelines_snapshots');
    }
};
