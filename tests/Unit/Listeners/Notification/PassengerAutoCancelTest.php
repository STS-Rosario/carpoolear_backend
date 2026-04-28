<?php

namespace Tests\Unit\Listeners\Notification;

use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use STS\Events\Passenger\AutoCancel as AutoCancelEvent;
use STS\Listeners\Notification\PassengerAutoCancel;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class PassengerAutoCancelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[RunInSeparateProcess]

    #[PreserveGlobalState(false)]
    public function test_handle_notifies_owner_and_passenger_when_both_participants_exist(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $ownerNotification = Mockery::mock('overload:STS\\Notifications\\AutoCancelPassengerRequestIfRequestLimitedNotification');
        $ownerNotification->shouldReceive('setAttribute')->once()->with('trip', $trip);
        $ownerNotification->shouldReceive('setAttribute')->once()->with('from', $to);
        $ownerNotification->shouldReceive('notify')->once()->with($from);

        $passengerNotification = Mockery::mock('overload:STS\\Notifications\\AutoCancelRequestIfRequestLimitedNotification');
        $passengerNotification->shouldReceive('setAttribute')->once()->with('trip', $trip);
        $passengerNotification->shouldReceive('setAttribute')->once()->with('from', $from);
        $passengerNotification->shouldReceive('notify')->once()->with($to);

        $listener = new PassengerAutoCancel;
        $listener->handle(new AutoCancelEvent($trip, $from, $to));

        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]

    #[PreserveGlobalState(false)]
    public function test_handle_skips_notifications_when_from_and_to_are_null(): void
    {
        $trip = Trip::factory()->create();

        $ownerNotification = Mockery::mock('overload:STS\\Notifications\\AutoCancelPassengerRequestIfRequestLimitedNotification');
        $ownerNotification->shouldNotReceive('setAttribute');
        $ownerNotification->shouldNotReceive('notify');

        $passengerNotification = Mockery::mock('overload:STS\\Notifications\\AutoCancelRequestIfRequestLimitedNotification');
        $passengerNotification->shouldNotReceive('setAttribute');
        $passengerNotification->shouldNotReceive('notify');

        $listener = new PassengerAutoCancel;
        $listener->handle(new AutoCancelEvent($trip, null, null));

        $this->assertTrue(true);
    }
}
