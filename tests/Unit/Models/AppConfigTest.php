<?php

namespace Tests\Unit\Models;

use STS\Models\AppConfig;
use Tests\TestCase;

class AppConfigTest extends TestCase
{
    public function test_value_accessor_decodes_json_scalar_string(): void
    {
        $key = 'cfg_str_'.uniqid('', true);
        AppConfig::query()->create([
            'key' => $key,
            'value' => json_encode('stored'),
            'is_laravel' => false,
        ]);

        $row = AppConfig::query()->where('key', $key)->firstOrFail();
        $this->assertSame('stored', $row->value);
    }

    public function test_value_accessor_decodes_json_object(): void
    {
        $key = 'cfg_obj_'.uniqid('', true);
        $payload = ['enabled' => true, 'n' => 3];
        AppConfig::query()->create([
            'key' => $key,
            'value' => json_encode($payload),
            'is_laravel' => false,
        ]);

        $row = AppConfig::query()->where('key', $key)->firstOrFail();
        $decoded = $row->value;
        $this->assertIsObject($decoded);
        $this->assertTrue($decoded->enabled);
        $this->assertSame(3, $decoded->n);
    }

    public function test_value_accessor_returns_null_for_invalid_json(): void
    {
        $key = 'cfg_bad_'.uniqid('', true);
        AppConfig::query()->create([
            'key' => $key,
            'value' => 'not-valid-json{',
            'is_laravel' => false,
        ]);

        $row = AppConfig::query()->where('key', $key)->firstOrFail();
        $this->assertNull($row->value);
    }

    public function test_is_laravel_is_cast_to_boolean(): void
    {
        $key = 'cfg_flag_'.uniqid('', true);
        AppConfig::query()->create([
            'key' => $key,
            'value' => json_encode(true),
            'is_laravel' => 1,
        ]);

        $row = AppConfig::query()->where('key', $key)->firstOrFail();
        $this->assertTrue($row->is_laravel);
    }
}
