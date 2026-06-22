<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Typed accessors for the AmoAccount settings JSON column.
 * Extracted to keep AmoAccount focused on identity and relationships.
 *
 * @property array|null $settings
 */
trait HasAccountSettings
{
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

    public function timezone(): string
    {
        return data_get($this->settings, 'timezone') ?: 'UTC';
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);

        $this->forceFill(['settings' => $settings])->save();
    }
}
