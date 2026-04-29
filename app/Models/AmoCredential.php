<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmoCredential extends Model
{
    public const AUTH_LONG_LIVED = 'long_lived_token';
    public const AUTH_OAUTH = 'oauth';

    protected $fillable = [
        'amo_account_id',
        'auth_type',
        'access_token',
        'refresh_token',
        'client_id',
        'client_secret',
        'redirect_uri',
        'token_expires_at',
        'scopes',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'client_id',
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }

    public function maskedAccessToken(): ?string
    {
        return self::maskSecret($this->access_token);
    }

    public static function maskSecret(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (mb_strlen($value) <= 10) {
            return str_repeat('*', mb_strlen($value));
        }

        return mb_substr($value, 0, 6).'******'.mb_substr($value, -4);
    }
}
