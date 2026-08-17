<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClientLogApiTest extends TestCase
{
    public function test_log_endpoint_sanitizes_and_writes_to_server_logs(): void
    {
        Log::spy();

        $this->postJson('api/log', [
            'log' => '<script>alert(1)</script>trip failed',
            'source' => 'trip_create',
            'context' => [
                'status' => 422,
                'errors' => ['car_id' => ['missing']],
            ],
        ])->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context = []) {
                return str_contains($message, 'ERROR IN APP [trip_create]: trip failed')
                    && ($context['context']['status'] ?? null) === 422;
            })
            ->once();
    }

    public function test_log_endpoint_returns_ok_without_writing_when_payload_is_empty(): void
    {
        Log::spy();

        $this->postJson('api/log', [])->assertOk();

        Log::shouldNotHaveReceived('info');
    }
}
