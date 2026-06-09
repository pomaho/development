<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardWidget extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'component_key',
        'sort_order',
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

    public function accountInstallations(): HasMany
    {
        return $this->hasMany(AmoAccountDashboardWidget::class);
    }
}
