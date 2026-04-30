<?php

namespace Tests\Unit\Listeners\Ratings;

use Mockery;
use STS\Events\Trip\Delete as TripDeleted;
use STS\Listeners\Ratings\CreateRatingDeleteTrip;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\DeleteTripNotification;
use STS\Repository\RatingRepository;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class CreateRatingDeleteTripListenerTest extends TestCase
{
    public function test_handle_does_nothing_when_trip_has_no_accepted_passengers(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory(),
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $ratingRepository = Mockery::mock(RatingRepository::class);
        $ratingRepository->shouldNotReceive('create');

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        (new CreateRatingDeleteTrip($ratingRepository))->handle(new TripDeleted($trip->fresh()));
    }

    public function test_handle_creates_pending_rating_and_notifies_each_accepted_passenger(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passengerUser = User::factory()->create();

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $trip = $trip->fresh();

        $ratingRepository = Mockery::mock(RatingRepository::class);
        $ratingRepository->shouldReceive('create')
            ->once()
            ->withArgs(function ($userFromId, $userToId, $tripId, $userToType, $userToState, $hash) use ($passengerUser, $driver, $trip) {
                return $userFromId === $passengerUser->id
                    && $userToId === $driver->id
                    && $tripId === $trip->id
                    && $userToType === Passenger::TYPE_CONDUCTOR
                    && $userToState === Passenger::STATE_ACCEPTED
                    && is_string($hash)
                    && strlen($hash) === 40;
            })
            ->andReturn((object) ['id' => 1]);

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $driver, $passengerUser) {
                if (! $notification instanceof DeleteTripNotification) {
                    return false;
                }

                $hash = $notification->getAttribute('hash');

                return $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('from')->is($driver)
                    && is_string($hash)
                    && strlen($hash) === 40
                    && $users instanceof User
                    && $users->is($passengerUser)
                    && is_string($channel);
            });

        (new CreateRatingDeleteTrip($ratingRepository))->handle(new TripDeleted($trip));
    }

    public function test_handle_repeats_rating_and_notification_per_accepted_passenger(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $first->id,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $second->id,
        ]);

        $trip = $trip->fresh();

        $ratingRepository = Mockery::mock(RatingRepository::class);
        $ratingRepository->shouldReceive('create')
            ->twice()
            ->withArgs(function ($userFromId, $userToId, $tripId, $userToType, $userToState, $hash) use ($first, $second, $driver, $trip) {
                return in_array($userFromId, [$first->id, $second->id], true)
                    && $userToId === $driver->id
                    && $tripId === $trip->id
                    && $userToType === Passenger::TYPE_CONDUCTOR
                    && $userToState === Passenger::STATE_ACCEPTED
                    && is_string($hash)
                    && strlen($hash) === 40;
            })
            ->andReturn((object) ['id' => 1]);

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(6)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $driver, $first, $second) {
                if (! $notification instanceof DeleteTripNotification) {
                    return false;
                }

                if (! $notification->getAttribute('trip')->is($trip)
                    || ! $notification->getAttribute('from')->is($driver)) {
                    return false;
                }

                $hash = $notification->getAttribute('hash');

                if (! is_string($hash) || strlen($hash) !== 40) {
                    return false;
                }

                return $users instanceof User
                    && ($users->is($first) || $users->is($second))
                    && is_string($channel);
            });

        (new CreateRatingDeleteTrip($ratingRepository))->handle(new TripDeleted($trip));
    }
}
