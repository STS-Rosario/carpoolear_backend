<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use STS\Http\Middleware\LoadConfig;
use STS\Models\AppConfig;
use Tests\TestCase;

class LoadConfigTest extends TestCase
{
    public function test_empty_config_table_still_invokes_next(): void
    {
        AppConfig::query()->delete();

        $middleware = new LoadConfig;
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('through', 201));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('through', $response->getContent());
    }

    public function test_laravel_flagged_rows_set_top_level_config(): void
    {
        AppConfig::query()->delete();

        $key = 'lc_laravel_'.uniqid('', true);
        AppConfig::query()->create([
            'key' => $key,
            'value' => json_encode('from-db'),
            'is_laravel' => true,
        ]);

        $middleware = new LoadConfig;
        $middleware->handle(Request::create('/', 'GET'), fn () => response('ok'));

        $this->assertSame('from-db', Config::get($key));
    }

    public function test_non_laravel_rows_set_under_carpoolear_namespace(): void
    {
        AppConfig::query()->delete();

        $key = 'lc_custom_'.uniqid('', true);
        $payload = ['enabled' => true, 'n' => 3];
        AppConfig::query()->create([
            'key' => $key,
            'value' => json_encode($payload),
            'is_laravel' => false,
        ]);

        $middleware = new LoadConfig;
        $middleware->handle(Request::create('/', 'GET'), fn () => response('ok'));

        $stored = Config::get('carpoolear.'.$key);
        $this->assertEquals(
            $payload,
            json_decode(json_encode($stored), true),
            'AppConfig decodes JSON objects as stdClass; compare as normalized array'
        );
    }

    public function test_multiple_rows_apply_in_order(): void
    {
        AppConfig::query()->delete();

        $k1 = 'lc_order_a_'.uniqid('', true);
        $k2 = 'lc_order_b_'.uniqid('', true);

        AppConfig::query()->create([
            'key' => $k1,
            'value' => json_encode(1),
            'is_laravel' => true,
        ]);
        AppConfig::query()->create([
            'key' => $k2,
            'value' => json_encode(2),
            'is_laravel' => true,
        ]);

        $middleware = new LoadConfig;
        $middleware->handle(Request::create('/', 'GET'), fn () => response('ok'));

        $this->assertSame(1, Config::get($k1));
        $this->assertSame(2, Config::get($k2));
    }
}
