<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadSyncSchedule extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const ENTITY_TYPE_LEADS = 'leads';
    public const ENTITY_TYPE_TASKS = 'tasks';
    public const ENTITY_TYPE_EVENTS = 'events';
    public const ENTITY_TYPE_CONTACTS = 'contacts';

    public const ENTITY_TYPES = [
        self::ENTITY_TYPE_LEADS,
        self::ENTITY_TYPE_TASKS,
        self::ENTITY_TYPE_EVENTS,
        self::ENTITY_TYPE_CONTACTS,
    ];

    protected $fillable = [
        'amo_account_id',
        'entity_type',
        'amo_pipeline_id',
        'pipeline_name',
        'interval_minutes',
        'lookback_days',
        'use_updated_at',
        'is_enabled',
        'last_run_at',
        'last_finished_at',
        'next_run_at',
        'last_status',
        'last_synced_count',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'amo_pipeline_id' => 'integer',
            'interval_minutes' => 'integer',
            'lookback_days' => 'integer',
            'use_updated_at' => 'boolean',
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('is_enabled', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('next_run_at')
                ->orWhere('next_run_at', '<=', now()));
    }
}
