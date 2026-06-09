<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStatisticsSyncRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'amo_account_id',
        'status',
        'period_from',
        'period_to',
        'completed_found',
        'completed_synced',
        'completion_events_found',
        'completion_events_synced',
        'open_found',
        'open_synced',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'datetime',
            'period_to' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function amoAccount(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class);
    }
}
