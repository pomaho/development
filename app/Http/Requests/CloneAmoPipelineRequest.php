<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloneAmoPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
