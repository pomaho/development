<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCustomFieldSnapshot extends Model
{
    protected $table = 'crm_custom_fields_snapshots';

    protected $fillable = [
        'amo_account_id',
        'entity_type',
        'amo_field_id',
        'name',
        'field_type',
        'code',
        'group_id',
        'sort',
        'is_required',
        'is_api_only',
        'enums',
        'required_statuses',
        'raw',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_api_only' => 'boolean',
            'enums' => 'array',
            'required_statuses' => 'array',
            'raw' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }
}
