<?php

namespace Tests\Unit\Services\Notifications;

use STS\Services\Notifications\BaseNotification;
use Tests\TestCase;

class BaseNotificationDefaultsTest extends TestCase
{
    public function test_force_email_defaults_to_false_on_anonymous_subclass(): void
    {
        $notification = new class extends BaseNotification
        {
            protected $via = [];
        };

        $ref = new \ReflectionProperty(BaseNotification::class, 'force_email');
        $ref->setAccessible(true);

        $this->assertFalse($ref->getValue($notification));
    }
}
