<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Passenger\Accept;
use STS\Listeners\Notification\PassengerAccept;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\AcceptPassengerNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class PassengerAcceptListenerTest extends TestCase
{
    /** @var array<int, mixed> */
    private array $captured = [];

    private function captureNotifications(): void
    {
        $this->captured = [];
        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->withArgs(function ($notification) {
                $this->captured[] = $notification;

                return true;
            });
    }

    private function acceptedPassenger(Trip $trip, User $user): void
    {
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);
    }

    private function acceptNotification(): ?AcceptPassengerNotification
    {
        return collect($this->captured)
            ->first(fn ($notification) => $notification instanceof AcceptPassengerNotification);
    }

    public function test_second_passenger_notification_attaches_the_created_group_chat(): void
    {
        $driver = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->acceptedPassenger($trip, $first);
        $this->acceptedPassenger($trip, $second);

        $this->captureNotifications();

        event(new Accept($trip->fresh(), $driver, $second));

        $notification = $this->acceptNotification();
        $this->assertNotNull($notification);

        $conversation = $notification->getAttribute('conversation');
        $this->assertNotNull($conversation);
        $this->assertSame(3, $conversation->users()->count());
        $this->assertTrue($conversation->users()->whereKey($second->id)->exists());
        $this->assertSame('conversation', $notification->getExtras()['type']);
    }

    public function test_third_passenger_notification_attaches_the_existing_group_chat(): void
    {
        $driver = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->captureNotifications();

        $this->acceptedPassenger($trip, $first);
        $this->acceptedPassenger($trip, $second);
        event(new Accept($trip->fresh(), $driver, $second)); // creates the group chat

        $this->acceptedPassenger($trip, $third);
        $this->captured = [];

        event(new Accept($trip->fresh(), $driver, $third));

        $notification = $this->acceptNotification();
        $this->assertNotNull($notification);

        $conversation = $notification->getAttribute('conversation');
        $this->assertNotNull($conversation);
        $this->assertSame(4, $conversation->users()->count());
        $this->assertTrue($conversation->users()->whereKey($third->id)->exists());
        $this->assertSame('conversation', $notification->getExtras()['type']);
    }

    public function test_handle_does_nothing_when_recipient_is_null(): void
    {
        $trip = Trip::factory()->create();
        $from = User::factory()->create();

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $this->app->make(PassengerAccept::class)->handle(new Accept($trip, $from, null));
    }
}
