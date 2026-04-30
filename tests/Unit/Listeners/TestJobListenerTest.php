<?php

namespace Tests\Unit\Listeners;

use Illuminate\Support\Facades\Log;
use STS\Events\User\Create as UserCreated;
use STS\Listeners\TestJob;
use Tests\TestCase;

class TestJobListenerTest extends TestCase
{
    public function test_handle_logs_when_user_create_event_is_processed(): void
    {
        Log::spy();

        (new TestJob)->handle(new UserCreated(901));

        Log::shouldHaveReceived('info')->with('create handler')->once();
    }
}
