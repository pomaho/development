<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPipelineStatusSnapshot extends Model
{
    protected $fillable = [
        'amo_account_id',
        'amo_pipeline_id',
        'amo_status_id',
        'name',
        'sort',
        'color',
        'type',
        'raw',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }
}
