<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amo_oauth_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('amo_account_id')->nullable()->constrained('amo_accounts')->nullOnDelete();
            $table->string('state')->unique();
            $table->string('name')->nullable();
            $table->string('base_domain')->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('authorization_code')->nullable();
            $table->string('redirect_uri')->nullable();
            $table->string('secrets_uri')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'status']);
            $table->index('base_domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amo_oauth_connections');
    }
};
