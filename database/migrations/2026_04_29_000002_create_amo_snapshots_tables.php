<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amo_users_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('amo_user_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('lang')->nullable();
            $table->json('rights');
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->boolean('is_admin')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('raw');
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->unique(['amo_account_id', 'amo_user_id']);
        });

        Schema::create('amo_roles_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('amo_role_id');
            $table->string('name');
            $table->json('rights');
            $table->json('users')->nullable();
            $table->json('raw');
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->unique(['amo_account_id', 'amo_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amo_roles_snapshots');
        Schema::dropIfExists('amo_users_snapshots');
    }
};
