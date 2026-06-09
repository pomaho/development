<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmoAccountDashboardWidget extends Model
{
    protected $fillable = [
        'amo_account_id',
        'dashboard_widget_id',
        'public_key',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class, 'amo_account_id');
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class, 'dashboard_widget_id');
    }
}
