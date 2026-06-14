<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_entity_snapshots', function (Blueprint $table): void {
            if (!Schema::hasIndex('crm_entity_snapshots', 'ces_account_type_created')) {
                $table->index(['amo_account_id', 'entity_type', 'entity_created_at'], 'ces_account_type_created');
            }
            if (!Schema::hasIndex('crm_entity_snapshots', 'ces_account_type_updated')) {
                $table->index(['amo_account_id', 'entity_type', 'entity_updated_at'], 'ces_account_type_updated');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_entity_snapshots', function (Blueprint $table): void {
            if (Schema::hasIndex('crm_entity_snapshots', 'ces_account_type_created')) {
                $table->dropIndex('ces_account_type_created');
            }
            if (Schema::hasIndex('crm_entity_snapshots', 'ces_account_type_updated')) {
                $table->dropIndex('ces_account_type_updated');
            }
        });
    }
};
