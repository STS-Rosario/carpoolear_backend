<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_returns_ok_when_database_is_up(): void
    {
        $this->getJson('api/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'database' => 'up',
            ]);
    }

    public function test_health_returns_503_when_database_is_down(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('getPdo')->andThrow(new \RuntimeException('connection refused'));
        DB::shouldReceive('connection')->andReturn($connection);

        $this->getJson('api/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'degraded',
                'database' => 'down',
            ]);
    }

    public function test_up_health_check_returns_ok(): void
    {
        $this->get('up')
            ->assertOk();
    }

    public function test_up_health_check_returns_500_when_database_is_down(): void
    {
        DB::shouldReceive('select')->andThrow(new \RuntimeException('connection refused'));

        $this->get('up')
            ->assertStatus(500);
    }
}
