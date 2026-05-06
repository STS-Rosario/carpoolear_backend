<?php

namespace Tests\Unit\Http\Controllers\Api\v1;

use STS\Http\Controllers\Api\v1\AuthController;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    public function test_log_returns_true(): void
    {
        $controller = $this->app->make(AuthController::class);

        $this->assertTrue($controller->log());
    }
}
