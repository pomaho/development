<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amo_accounts', function (Blueprint $table): void {
            $table->string('webhook_key')->nullable()->after('auth_status');
        });

        Schema::table('amo_accounts', function (Blueprint $table): void {
            $table->unique('webhook_key');
        });

        DB::table('amo_accounts')
            ->whereNull('webhook_key')
            ->cursor()
            ->each(function (object $account): void {
                DB::table('amo_accounts')
                    ->where('id', $account->id)
                    ->update(['webhook_key' => Str::random(48)]);
            });

        Schema::create('amo_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('amo_account_id')->constrained('amo_accounts')->cascadeOnDelete();
            $table->string('event_type')->index();
            $table->string('entity_type')->nullable()->index();
            $table->string('entity_id')->nullable()->index();
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amo_webhook_events');

        Schema::table('amo_accounts', function (Blueprint $table): void {
            $table->dropUnique(['webhook_key']);
            $table->dropColumn('webhook_key');
        });
    }
};
