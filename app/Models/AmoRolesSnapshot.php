<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmoRolesSnapshot extends Model
{
    protected $fillable = [
        'amo_account_id',
        'amo_role_id',
        'name',
        'rights',
        'users',
        'raw',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'rights' => 'array',
            'users' => 'array',
            'raw' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }
}
