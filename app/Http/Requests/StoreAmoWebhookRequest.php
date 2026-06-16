<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Amo\Webhooks\AmoWebhooksRegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAmoWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sync', $this->route('amo_account')) === true;
    }

    public function rules(): array
    {
        return [
            'destination' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(AmoWebhooksRegistrationService::allValidEvents())],
        ];
    }

    public function messages(): array
    {
        return [
            'events.required' => 'Выберите хотя бы одно событие.',
            'events.min' => 'Выберите хотя бы одно событие.',
            'events.*.in' => 'Одно из выбранных событий недопустимо.',
        ];
    }
}
