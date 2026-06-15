<?php

namespace App\Models;

use App\Casts\CustomFieldValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmEntitySnapshot extends Model
{
    protected $fillable = [
        'amo_account_id',
        'entity_type',
        'external_id',
        'name',
        'pipeline_id',
        'status_id',
        'responsible_user_id',
        'entity_created_at',
        'entity_updated_at',
        'entity_closed_at',
        'custom_fields_values',
        'embedded',
        'raw',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'entity_created_at' => 'datetime',
            'entity_updated_at' => 'datetime',
            'entity_closed_at' => 'datetime',
            'custom_fields_values' => CustomFieldValues::class,
            'embedded' => 'array',
            'raw' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }
}
