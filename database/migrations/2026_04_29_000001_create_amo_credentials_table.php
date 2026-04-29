<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amo_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->string('auth_type');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('redirect_uri')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamps();

            $table->unique('amo_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amo_credentials');
    }
};
