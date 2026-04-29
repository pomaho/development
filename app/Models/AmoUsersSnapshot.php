<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmoUsersSnapshot extends Model
{
    protected $fillable = [
        'amo_account_id',
        'amo_user_id',
        'name',
        'email',
        'lang',
        'rights',
        'role_id',
        'group_id',
        'is_admin',
        'is_active',
        'raw',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'rights' => 'array',
            'raw' => 'array',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }
}
