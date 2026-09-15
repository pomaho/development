<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every lead-status funnel report filters crm_entity_snapshots events by
 * JSON_EXTRACT(raw,'$.type')='lead_status_changed' — MySQL has no index it can use for
 * that, so it either scans the whole (large, growing) table or picks a much worse index
 * than the date-range one that's actually available, mis-costing the unindexable
 * predicate. A generated column pulls the type out of raw into something indexable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('crm_entity_snapshots', 'event_type')) {
            DB::statement(
                "ALTER TABLE crm_entity_snapshots ".
                "ADD COLUMN event_type VARCHAR(64) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(raw, '$.type'))) VIRTUAL AFTER raw, ".
                "ADD INDEX ces_account_type_eventtype_created (amo_account_id, entity_type, event_type, entity_created_at)"
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_entity_snapshots', 'event_type')) {
            DB::statement(
                'ALTER TABLE crm_entity_snapshots '.
                'DROP INDEX ces_account_type_eventtype_created, '.
                'DROP COLUMN event_type'
            );
        }
    }
};
