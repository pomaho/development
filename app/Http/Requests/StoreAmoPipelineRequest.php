<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAmoPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort' => ['required', 'integer', 'min:1', 'max:10000'],
            'is_main' => ['nullable', 'boolean'],
            'is_unsorted_on' => ['nullable', 'boolean'],
            'statuses' => ['required', 'array', 'min:1', 'max:98'],
            'statuses.*.id' => ['nullable', 'integer', 'in:142,143'],
            'statuses.*.name' => ['nullable', 'string', 'max:255'],
            'statuses.*.sort' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'statuses.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function validatedPipelineData(): array
    {
        $data = $this->validated();
        $data['is_main'] = $this->boolean('is_main');
        $data['is_unsorted_on'] = $this->boolean('is_unsorted_on', true);
        $data['statuses'] = collect($data['statuses'])
            ->filter(fn (array $status) => filled($status['name'] ?? null))
            ->values()
            ->all();

        return $data;
    }
}
