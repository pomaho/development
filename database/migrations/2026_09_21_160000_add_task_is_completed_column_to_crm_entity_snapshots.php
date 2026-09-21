<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * buildStatistics() filters crm_entity_snapshots (entity_type='tasks') by
 * JSON_EXTRACT(raw,'$.is_completed') — no usable index for that, so a query for the
 * (small, sparse) set of currently-open tasks had to scan tens of thousands of rows in
 * id order to find matches. A generated column pulls the flag out of raw into something
 * indexable, same approach as event_type in the 2026_09_15 migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('crm_entity_snapshots', 'task_is_completed')) {
            DB::statement(
                'ALTER TABLE crm_entity_snapshots '.
                "ADD COLUMN task_is_completed TINYINT(1) GENERATED ALWAYS AS (JSON_EXTRACT(raw, '$.is_completed') = true) VIRTUAL AFTER raw, ".
                'ADD INDEX ces_account_type_taskcompleted_created (amo_account_id, entity_type, task_is_completed, entity_created_at)'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_entity_snapshots', 'task_is_completed')) {
            DB::statement(
                'ALTER TABLE crm_entity_snapshots '.
                'DROP INDEX ces_account_type_taskcompleted_created, '.
                'DROP COLUMN task_is_completed'
            );
        }
    }
};
