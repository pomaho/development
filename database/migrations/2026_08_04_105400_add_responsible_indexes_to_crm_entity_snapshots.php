<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_entity_snapshots', function (Blueprint $table): void {
            if (!Schema::hasIndex('crm_entity_snapshots', 'ces_account_type_responsible_id')) {
                $table->index(['amo_account_id', 'entity_type', 'responsible_user_id', 'id'], 'ces_account_type_responsible_id');
            }
            if (!Schema::hasIndex('crm_entity_snapshots', 'ces_account_type_id')) {
                $table->index(['amo_account_id', 'entity_type', 'id'], 'ces_account_type_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_entity_snapshots', function (Blueprint $table): void {
            if (Schema::hasIndex('crm_entity_snapshots', 'ces_account_type_responsible_id')) {
                $table->dropIndex('ces_account_type_responsible_id');
            }
            if (Schema::hasIndex('crm_entity_snapshots', 'ces_account_type_id')) {
                $table->dropIndex('ces_account_type_id');
            }
        });
    }
};
