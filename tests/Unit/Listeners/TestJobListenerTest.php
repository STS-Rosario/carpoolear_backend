<?php

namespace Tests\Unit\Listeners;

use STS\Events\User\Create as UserCreated;
use STS\Listeners\TestJob;
use Tests\TestCase;

class TestJobListenerTest extends TestCase
{
    public function test_handle_completes_without_error_when_user_create_event_is_processed(): void
    {
        (new TestJob)->handle(new UserCreated(901));

        $this->assertTrue(true);
    }
}
