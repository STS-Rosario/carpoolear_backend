<?php

namespace Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use STS\Http\Controllers\Api\v1\DebugController;
use Tests\TestCase;

class DebugControllerTest extends TestCase
{
    public function test_controller_is_instantiable(): void
    {
        $controller = new DebugController;

        $this->assertInstanceOf(DebugController::class, $controller);
    }

    public function test_log_does_not_emit_when_log_key_is_absent(): void
    {
        Log::spy();

        (new DebugController)->log(Request::create('/', 'POST', []));

        Log::shouldNotHaveReceived('info');
    }

    public function test_log_emits_prefixed_message_when_log_key_is_present(): void
    {
        Log::spy();

        (new DebugController)->log(Request::create('/', 'POST', ['log' => 'db connection failed']));

        Log::shouldHaveReceived('info')->with('ERROR IN APP: db connection failed')->once();
    }
}
