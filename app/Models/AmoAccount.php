<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AmoAccount extends Model
{
    protected $fillable = [
        'owner_user_id',
        'name',
        'base_domain',
        'account_id',
        'is_active',
        'auth_status',
        'notes',
        'settings',
        'last_successful_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'last_successful_sync_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function credentials(): HasOne
    {
        return $this->hasOne(AmoCredential::class);
    }

    public function usersSnapshots(): HasMany
    {
        return $this->hasMany(AmoUsersSnapshot::class);
    }

    public function rolesSnapshots(): HasMany
    {
        return $this->hasMany(AmoRolesSnapshot::class);
    }

    public function apiRequestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    public function responsibilityRedistributionRuns(): HasMany
    {
        return $this->hasMany(ResponsibilityRedistributionRun::class);
    }

    public function taskStatisticsSyncRuns(): HasMany
    {
        return $this->hasMany(TaskStatisticsSyncRun::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
