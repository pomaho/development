<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amo_account_dashboard_widgets')) {
            return;
        }

        Schema::create('amo_account_dashboard_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->foreignId('dashboard_widget_id')->constrained('dashboard_widgets')->cascadeOnDelete();
            $table->string('public_key')->unique();
            $table->boolean('is_enabled')->default(true)->index();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['amo_account_id', 'dashboard_widget_id'], 'account_dashboard_widget_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amo_account_dashboard_widgets');
    }
};
