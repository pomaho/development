<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmoOAuthConnection extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SECRETS_RECEIVED = 'secrets_received';
    public const STATUS_CODE_RECEIVED = 'code_received';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_FAILED = 'failed';

    protected $table = 'amo_oauth_connections';

    protected $fillable = [
        'owner_user_id',
        'amo_account_id',
        'state',
        'name',
        'base_domain',
        'client_id',
        'client_secret',
        'authorization_code',
        'redirect_uri',
        'secrets_uri',
        'scopes',
        'status',
        'error_message',
        'expires_at',
        'connected_at',
    ];

    protected $hidden = [
        'client_id',
        'client_secret',
        'authorization_code',
    ];

    protected function casts(): array
    {
        return [
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'authorization_code' => 'encrypted',
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }
}
