<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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

    public function leadSyncSchedules(): HasMany
    {
        return $this->hasMany(LeadSyncSchedule::class);
    }

    public function dashboardWidgetInstallations(): HasMany
    {
        return $this->hasMany(AmoAccountDashboardWidget::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function taskStatisticsLastSuccessfulSyncAt(): ?Carbon
    {
        $value = data_get($this->settings, 'task_statistics.last_successful_sync_at');

        return $value ? Carbon::parse($value) : null;
    }

    public function markTaskStatisticsSyncedUntil(Carbon $syncedUntil): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, 'task_statistics.last_successful_sync_at', $syncedUntil->toIso8601String());

        $this->forceFill(['settings' => $settings])->save();
    }
}
