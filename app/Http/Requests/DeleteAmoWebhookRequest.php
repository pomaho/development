<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAmoWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sync', $this->route('amo_account')) === true;
    }

    public function rules(): array
    {
        return [
            'destination' => ['required', 'url', 'max:500'],
        ];
    }
}
