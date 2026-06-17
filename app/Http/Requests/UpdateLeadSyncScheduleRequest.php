<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\LeadSyncSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadSyncScheduleRequest extends FormRequest
{
    private const VALID_INTERVALS = [15, 30, 60, 180, 360, 720, 1440];

    public function authorize(): bool
    {
        $account = $this->route('amo_account');

        return $this->user()?->can('sync', $account) === true;
    }

    public function rules(): array
    {
        $account = $this->route('amo_account');
        $schedule = $this->route('lead_sync_schedule');
        $entityType = $schedule?->entity_type ?? $this->input('entity_type');

        $rules = [
            'interval_minutes' => ['required', 'integer', Rule::in(self::VALID_INTERVALS)],
            'lookback_days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_enabled' => ['nullable', 'boolean'],
        ];

        if ($entityType === LeadSyncSchedule::ENTITY_TYPE_LEADS) {
            $rules['amo_pipeline_id'] = [
                'required',
                'integer',
                Rule::exists('crm_pipelines_snapshots', 'amo_pipeline_id')
                    ->where('amo_account_id', $account?->id),
                Rule::unique('lead_sync_schedules', 'amo_pipeline_id')
                    ->where('amo_account_id', $account?->id)
                    ->where('entity_type', LeadSyncSchedule::ENTITY_TYPE_LEADS)
                    ->ignore($schedule?->id),
            ];
        } else {
            $rules['amo_pipeline_id'] = ['nullable'];
        }

        return $rules;
    }
}
