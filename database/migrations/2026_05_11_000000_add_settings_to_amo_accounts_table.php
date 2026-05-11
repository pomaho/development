<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amo_accounts', function (Blueprint $table): void {
            $table->json('settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('amo_accounts', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });
    }
};
