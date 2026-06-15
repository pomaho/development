<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Typed cast for CrmEntitySnapshot.custom_fields_values.
 * Enforces the AmoCRM custom field value schema:
 * [ ['field_id' => int, 'field_name' => string, 'values' => array] ]
 */
final class CustomFieldValues implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [];
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string|null
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('CustomFieldValues cast expects an array.');
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
