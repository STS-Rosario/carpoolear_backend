<?php

namespace Tests\Unit\Listeners\Notification;

use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use STS\Events\Passenger\AutoRequest as AutoRequestEvent;
use STS\Listeners\Notification\PassengerAutoRequest;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class PassengerAutoRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[RunInSeparateProcess]

    #[PreserveGlobalState(false)]
    public function test_handle_creates_notification_sets_attributes_and_notifies_when_recipient_exists(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\AutoRequestPassengerNotification');
        $notificationMock->shouldReceive('setAttribute')->once()->with('trip', $trip);
        $notificationMock->shouldReceive('setAttribute')->once()->with('from', $from);
        $notificationMock->shouldReceive('notify')->once()->with($to);

        $listener = new PassengerAutoRequest;
        $listener->handle(new AutoRequestEvent($trip, $from, $to));

        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]

    #[PreserveGlobalState(false)]
    public function test_handle_does_nothing_when_recipient_is_null(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\AutoRequestPassengerNotification');
        $notificationMock->shouldNotReceive('setAttribute');
        $notificationMock->shouldNotReceive('notify');

        $listener = new PassengerAutoRequest;
        $listener->handle(new AutoRequestEvent($trip, $from, null));

        $this->assertTrue(true);
    }
}
