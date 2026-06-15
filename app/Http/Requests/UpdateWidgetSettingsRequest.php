<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWidgetSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('amo_account');

        return $this->user()?->can('update', $account) === true;
    }

    public function rules(): array
    {
        return [
            'pipeline_id' => ['nullable', 'integer', 'min:1'],
            'recruiter_field_id' => ['nullable', 'integer', 'min:1'],
            'manager_field_id' => ['nullable', 'integer', 'min:1'],
            'team_field_id' => ['nullable', 'integer', 'min:1'],
            'city_field_id' => ['nullable', 'integer', 'min:1'],
            'source_field_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
