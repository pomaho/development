<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPipelineSnapshot extends Model
{
    protected $table = 'crm_pipelines_snapshots';

    protected $fillable = [
        'amo_account_id',
        'amo_pipeline_id',
        'name',
        'sort',
        'is_main',
        'is_unsorted_on',
        'is_archive',
        'raw',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_unsorted_on' => 'boolean',
            'is_archive' => 'boolean',
            'raw' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }
}
