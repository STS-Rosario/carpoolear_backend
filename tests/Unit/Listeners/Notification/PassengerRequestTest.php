<?php

namespace Tests\Unit\Listeners\Notification;

use Mockery;
use STS\Events\Passenger\Request as RequestEvent;
use STS\Listeners\Notification\PassengerRequest;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class PassengerRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_creates_notification_sets_attributes_and_notifies_when_recipient_exists(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\RequestPassengerNotification');
        $notificationMock->shouldReceive('setAttribute')->once()->with('trip', $trip);
        $notificationMock->shouldReceive('setAttribute')->once()->with('from', $from);
        $notificationMock->shouldReceive('notify')->once()->with($to);

        $listener = new PassengerRequest;
        $listener->handle(new RequestEvent($trip, $from, $to));

        $this->assertTrue(true);
    }

    public function test_handle_does_nothing_when_recipient_is_null(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\RequestPassengerNotification');
        $notificationMock->shouldNotReceive('setAttribute');
        $notificationMock->shouldNotReceive('notify');

        $listener = new PassengerRequest;
        $listener->handle(new RequestEvent($trip, $from, null));

        $this->assertTrue(true);
    }
}
