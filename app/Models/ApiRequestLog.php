<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    protected $fillable = [
        'amo_account_id',
        'method',
        'url',
        'status_code',
        'request_payload',
        'response_payload',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }
}
