<?php

namespace App\Http\Requests;

use App\Models\AmoCredential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAmoAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $editingExisting = (bool) $this->route('amo_account');

        return [
            'name' => ['required', 'string', 'max:255'],
            'base_domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+\\.amocrm\\.(ru|com)$/i', 'not_regex:/^https?:\\/\\//i', Rule::unique('amo_accounts', 'base_domain')->ignore($this->route('amo_account'))],
            'auth_type' => ['required', Rule::in([AmoCredential::AUTH_LONG_LIVED, AmoCredential::AUTH_OAUTH])],
            'access_token' => [Rule::requiredIf($this->input('auth_type') === AmoCredential::AUTH_LONG_LIVED && ! $editingExisting), 'nullable', 'string'],
            'client_id' => [Rule::requiredIf($this->input('auth_type') === AmoCredential::AUTH_OAUTH && ! $editingExisting), 'nullable', 'string'],
            'client_secret' => [Rule::requiredIf($this->input('auth_type') === AmoCredential::AUTH_OAUTH && ! $editingExisting), 'nullable', 'string'],
            'redirect_uri' => [Rule::requiredIf($this->input('auth_type') === AmoCredential::AUTH_OAUTH), 'nullable', 'url'],
            'refresh_token' => ['nullable', 'string'],
            'token_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('base_domain')) {
            $this->merge([
                'base_domain' => mb_strtolower(trim(preg_replace('#^https?://#i', '', (string) $this->input('base_domain')), '/')),
            ]);
        }
    }
}
