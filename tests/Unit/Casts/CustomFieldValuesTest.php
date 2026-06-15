<?php

namespace Tests\Unit\Casts;

use App\Casts\CustomFieldValues;
use App\Models\CrmEntitySnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CustomFieldValuesTest extends TestCase
{
    use RefreshDatabase;

    private function cast(): CustomFieldValues
    {
        return new CustomFieldValues();
    }

    private function model(): CrmEntitySnapshot
    {
        return new CrmEntitySnapshot();
    }

    public function test_get_decodes_json_string_to_array(): void
    {
        $json = json_encode([['field_id' => 1, 'values' => [['value' => 'foo']]]]);
        $result = $this->cast()->get($this->model(), 'custom_fields_values', $json, []);

        $this->assertIsArray($result);
        $this->assertSame(1, $result[0]['field_id']);
    }

    public function test_get_returns_empty_array_for_null(): void
    {
        $result = $this->cast()->get($this->model(), 'custom_fields_values', null, []);

        $this->assertSame([], $result);
    }

    public function test_get_returns_empty_array_for_invalid_json(): void
    {
        $result = $this->cast()->get($this->model(), 'custom_fields_values', 'not-json', []);

        $this->assertSame([], $result);
    }

    public function test_get_returns_array_when_already_decoded(): void
    {
        $array = [['field_id' => 5]];
        $result = $this->cast()->get($this->model(), 'custom_fields_values', $array, []);

        $this->assertSame($array, $result);
    }

    public function test_set_encodes_array_to_json(): void
    {
        $data = [['field_id' => 2, 'values' => [['value' => 'bar']]]];
        $result = $this->cast()->set($this->model(), 'custom_fields_values', $data, []);

        $this->assertIsString($result);
        $this->assertSame($data, json_decode($result, true));
    }

    public function test_set_returns_null_for_null_input(): void
    {
        $result = $this->cast()->set($this->model(), 'custom_fields_values', null, []);

        $this->assertNull($result);
    }

    public function test_set_throws_for_non_array_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cast()->set($this->model(), 'custom_fields_values', 'invalid', []);
    }

    public function test_model_cast_round_trips_through_database(): void
    {
        $account = \App\Models\AmoAccount::query()->create([
            'name' => 'Test',
            'base_domain' => 'test.amocrm.ru',
        ]);

        $data = [['field_id' => 99, 'field_name' => 'Тест', 'values' => [['value' => 'yes', 'enum_id' => 1]]]];

        $snapshot = CrmEntitySnapshot::query()->create([
            'amo_account_id' => $account->id,
            'entity_type' => 'leads',
            'external_id' => 1,
            'custom_fields_values' => $data,
            'raw' => [],
            'synced_at' => now(),
        ]);

        $snapshot->refresh();

        $this->assertIsArray($snapshot->custom_fields_values);
        $this->assertSame(99, $snapshot->custom_fields_values[0]['field_id']);
        $this->assertSame('Тест', $snapshot->custom_fields_values[0]['field_name']);
    }
}
