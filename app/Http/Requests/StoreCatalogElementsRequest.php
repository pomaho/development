<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogElementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('amo_account');

        return $this->user()?->can('sync', $account) === true;
    }

    public function rules(): array
    {
        return [
            'catalog_id' => ['required', 'integer', 'min:1'],
            'elements' => ['required', 'string', 'max:10000'],
        ];
    }
}
