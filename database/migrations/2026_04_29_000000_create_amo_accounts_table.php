<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amo_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('base_domain')->unique();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->string('auth_status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amo_accounts');
    }
};
