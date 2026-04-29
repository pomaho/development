<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationModule extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_enabled',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'array',
        ];
    }
}
