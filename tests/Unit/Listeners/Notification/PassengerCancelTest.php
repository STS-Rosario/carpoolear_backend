<?php

namespace Tests\Unit\Listeners\Notification;

use Mockery;
use STS\Events\Passenger\Cancel as CancelEvent;
use STS\Listeners\Notification\PassengerCancel;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class PassengerCancelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_sets_driver_flag_true_when_canceled_by_driver_and_notifies(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\CancelPassengerNotification');
        $notificationMock->shouldReceive('setAttribute')->once()->with('trip', $trip);
        $notificationMock->shouldReceive('setAttribute')->once()->with('from', $from);
        $notificationMock->shouldReceive('setAttribute')->once()->with('is_driver', true);
        $notificationMock->shouldReceive('setAttribute')->once()->with('canceledState', Passenger::CANCELED_DRIVER);
        $notificationMock->shouldReceive('notify')->once()->with($to);

        $listener = new PassengerCancel;
        $listener->handle(new CancelEvent($trip, $from, $to, Passenger::CANCELED_DRIVER));

        $this->assertTrue(true);
    }

    public function test_handle_sets_driver_flag_false_for_non_driver_state_and_skips_when_to_null(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\CancelPassengerNotification');
        $notificationMock->shouldReceive('setAttribute')->once()->with('trip', $trip);
        $notificationMock->shouldReceive('setAttribute')->once()->with('from', $from);
        $notificationMock->shouldReceive('setAttribute')->once()->with('is_driver', false);
        $notificationMock->shouldReceive('setAttribute')->once()->with('canceledState', Passenger::CANCELED_PASSENGER);
        $notificationMock->shouldReceive('notify')->once()->with($to);

        $listener = new PassengerCancel;
        $listener->handle(new CancelEvent($trip, $from, $to, Passenger::CANCELED_PASSENGER));

        $notificationMockNull = Mockery::mock('overload:STS\\Notifications\\CancelPassengerNotification');
        $notificationMockNull->shouldNotReceive('setAttribute');
        $notificationMockNull->shouldNotReceive('notify');

        $listener->handle(new CancelEvent($trip, $from, null, Passenger::CANCELED_PASSENGER));

        $this->assertTrue(true);
    }
}
