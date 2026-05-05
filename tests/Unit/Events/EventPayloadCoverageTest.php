<?php

namespace Tests\Unit\Events;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use STS\Events\Notification\NotificationSending;
use STS\Events\Passenger\Accept as PassengerAccept;
use STS\Events\Passenger\AutoCancel as PassengerAutoCancel;
use STS\Events\Passenger\AutoRequest as PassengerAutoRequest;
use STS\Events\Passenger\Cancel as PassengerCancel;
use STS\Events\Passenger\Reject as PassengerReject;
use STS\Events\Passenger\Request as PassengerRequest;
use STS\Events\Rating\PendingRate;
use STS\Events\TestEvent;
use STS\Events\Trip\Alert\HourLeft;
use STS\Events\Trip\Alert\RequestNotAnswer;
use STS\Events\Trip\Alert\RequestRemainder;
use STS\Events\Trip\Create as TripCreate;
use STS\Events\Trip\Delete as TripDelete;
use STS\Events\Trip\Update as TripUpdate;
use STS\Events\User\Create as UserCreate;
use STS\Events\User\Reset as UserReset;
use STS\Events\User\Update as UserUpdate;

class EventPayloadCoverageTest extends TestCase
{
    #[DataProvider('publicPayloadEventsProvider')]
    public function test_public_payload_events_store_constructor_arguments(
        string $eventClass,
        array $args,
        array $expectedPublicProperties
    ): void {
        $event = new $eventClass(...$args);

        foreach ($expectedPublicProperties as $property => $expectedValue) {
            $this->assertTrue(
                property_exists($event, $property),
                sprintf('%s should expose property $%s', $eventClass, $property)
            );
            $this->assertSame($expectedValue, $event->{$property});
        }
    }

    #[DataProvider('eventsWithEmptyBroadcastChannelsProvider')]
    public function test_events_broadcast_on_returns_empty_channel_array(string $eventClass, array $args): void
    {
        $event = new $eventClass(...$args);
        $channels = $event->broadcastOn();

        $this->assertIsArray($channels);
        $this->assertSame([], $channels);
    }

    public function test_user_update_event_stores_constructor_id_in_protected_property(): void
    {
        $id = 98765;
        $event = new UserUpdate($id);

        $readProtectedId = function () {
            return $this->id;
        };

        $reader = \Closure::bind($readProtectedId, $event, UserUpdate::class);
        $this->assertNotFalse($reader);
        $this->assertSame($id, $reader());
    }

    public static function publicPayloadEventsProvider(): array
    {
        $trip = (object) ['id' => 1001];
        $from = (object) ['id' => 1002];
        $to = (object) ['id' => 1003];
        $notification = (object) ['id' => 1004];
        $user = (object) ['id' => 1005];
        $channel = 'mail';
        $state = 'driver_cancel';
        $hash = 'pending-rate-hash';

        return [
            'notification sending payload' => [
                NotificationSending::class,
                [$notification, $user, $channel],
                ['notification' => $notification, 'user' => $user, 'channel' => $channel],
            ],
            'passenger accept payload' => [
                PassengerAccept::class,
                [$trip, $from, $to],
                ['trip' => $trip, 'from' => $from, 'to' => $to],
            ],
            'passenger accept payload supports null to' => [
                PassengerAccept::class,
                [$trip, $from],
                ['trip' => $trip, 'from' => $from, 'to' => null],
            ],
            'passenger auto cancel payload' => [
                PassengerAutoCancel::class,
                [$trip, $from, $to],
                ['trip' => $trip, 'from' => $from, 'to' => $to],
            ],
            'passenger auto request payload' => [
                PassengerAutoRequest::class,
                [$trip, $from, $to],
                ['trip' => $trip, 'from' => $from, 'to' => $to],
            ],
            'passenger cancel payload' => [
                PassengerCancel::class,
                [$trip, $from, $to, $state],
                ['trip' => $trip, 'from' => $from, 'to' => $to, 'canceledState' => $state],
            ],
            'passenger reject payload' => [
                PassengerReject::class,
                [$trip, $from, $to],
                ['trip' => $trip, 'from' => $from, 'to' => $to],
            ],
            'passenger request payload' => [
                PassengerRequest::class,
                [$trip, $from, $to],
                ['trip' => $trip, 'from' => $from, 'to' => $to],
            ],
            'pending rate payload' => [
                PendingRate::class,
                [$to, $trip, $hash],
                ['to' => $to, 'trip' => $trip, 'hash' => $hash],
            ],
            'trip alert hour left payload' => [
                HourLeft::class,
                [$trip, $to],
                ['trip' => $trip, 'to' => $to],
            ],
            'trip alert request not answer payload' => [
                RequestNotAnswer::class,
                [$trip, $to],
                ['trip' => $trip, 'to' => $to],
            ],
            'trip alert request remainder payload' => [
                RequestRemainder::class,
                [$trip],
                ['trip' => $trip],
            ],
            'trip create payload' => [
                TripCreate::class,
                [$trip],
                ['trip' => $trip],
            ],
            'trip delete payload' => [
                TripDelete::class,
                [$trip],
                ['trip' => $trip],
            ],
            'trip update payload' => [
                TripUpdate::class,
                [$trip],
                ['trip' => $trip],
            ],
            'user create payload' => [
                UserCreate::class,
                [123],
                ['id' => 123],
            ],
            'user reset payload' => [
                UserReset::class,
                [456, 'reset-token'],
                ['id' => 456, 'token' => 'reset-token'],
            ],
        ];
    }

    public static function eventsWithEmptyBroadcastChannelsProvider(): array
    {
        return [
            'notification sending' => [NotificationSending::class, [1, 2, 'database']],
            'passenger accept' => [PassengerAccept::class, [1, 2, 3]],
            'passenger auto cancel' => [PassengerAutoCancel::class, [1, 2, 3]],
            'passenger auto request' => [PassengerAutoRequest::class, [1, 2, 3]],
            'passenger cancel' => [PassengerCancel::class, [1, 2, 3, 'state']],
            'passenger reject' => [PassengerReject::class, [1, 2, 3]],
            'passenger request' => [PassengerRequest::class, [1, 2, 3]],
            'pending rate' => [PendingRate::class, [1, 2, 'hash']],
            'test event' => [TestEvent::class, []],
            'trip hour left' => [HourLeft::class, [1, 2]],
            'trip request not answer' => [RequestNotAnswer::class, [1, 2]],
            'trip request remainder' => [RequestRemainder::class, [1]],
            'trip create' => [TripCreate::class, [1]],
            'trip delete' => [TripDelete::class, [1]],
            'trip update' => [TripUpdate::class, [1]],
            'user create' => [UserCreate::class, [1]],
            'user reset' => [UserReset::class, [1, 't']],
            'user update' => [UserUpdate::class, [1]],
        ];
    }
}
