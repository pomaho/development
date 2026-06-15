<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('amo_account');

        return $this->user()?->can('sync', $account) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'can_add_elements' => ['nullable', 'boolean'],
            'can_show_in_cards' => ['nullable', 'boolean'],
            'can_link_multiple' => ['nullable', 'boolean'],
        ];
    }
}
