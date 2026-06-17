<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\LeadSyncSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadSyncScheduleRequest extends FormRequest
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
        $entityType = $this->input('entity_type');

        $rules = [
            'entity_type' => ['required', Rule::in(LeadSyncSchedule::ENTITY_TYPES)],
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
                    ->where('entity_type', LeadSyncSchedule::ENTITY_TYPE_LEADS),
            ];
        } else {
            $rules['amo_pipeline_id'] = ['nullable'];
            $rules['entity_type'][] = Rule::unique('lead_sync_schedules', 'entity_type')
                ->where('amo_account_id', $account?->id)
                ->whereNull('amo_pipeline_id');
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'entity_type.unique' => 'Расписание для этого типа сущности уже существует.',
            'amo_pipeline_id.unique' => 'Расписание для этой воронки уже существует.',
        ];
    }
}
