<?php

namespace Tests\Unit\Listeners\Subscriptions;

use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Events\Trip\Create as TripCreated;
use STS\Listeners\Subscriptions\OnNewTrip;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\SubscriptionMatchNotification;
use STS\Repository\SubscriptionsRepository;
use STS\Repository\UserRepository;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class OnNewTripListenerTest extends TestCase
{
    public function test_handle_sends_nothing_when_subscription_search_returns_no_rows(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $trip = $trip->fresh();

        $userRepo = Mockery::mock(UserRepository::class);
        $subRepo = Mockery::mock(SubscriptionsRepository::class);
        $subRepo->shouldReceive('search')
            ->once()
            ->with(
                Mockery::on(fn (User $u) => $u->is($driver)),
                Mockery::on(fn (Trip $t) => $t->is($trip))
            )
            ->andReturn(collect());

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        (new OnNewTrip($userRepo, $subRepo))->handle(new TripCreated($trip));
    }

    public function test_handle_notifies_each_matched_subscriber_on_all_channels(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id, 'to_town' => 'Destination X']);
        $trip = $trip->fresh();

        $subscriber = User::factory()->create(['name' => 'Matched Subscriber']);

        $userRepo = Mockery::mock(UserRepository::class);
        $subRepo = Mockery::mock(SubscriptionsRepository::class);
        $subRepo->shouldReceive('search')
            ->once()
            ->with(
                Mockery::on(fn (User $u) => $u->is($driver)),
                Mockery::on(fn (Trip $t) => $t->is($trip))
            )
            ->andReturn(collect([(object) ['user' => $subscriber]]));

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $subscriber) {
                if (! $notification instanceof SubscriptionMatchNotification) {
                    return false;
                }

                return $notification->getAttribute('trip')->is($trip)
                    && $users instanceof User
                    && $users->is($subscriber)
                    && is_string($channel);
            });

        (new OnNewTrip($userRepo, $subRepo))->handle(new TripCreated($trip));
    }

    public function test_handle_logs_concatenated_context_when_notification_send_fails(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'to_town' => 'San Luis',
        ]);
        $trip = $trip->fresh();

        $subscriber = User::factory()->create([
            'name' => 'Casey Lee',
        ]);

        $userRepo = Mockery::mock(UserRepository::class);
        $subRepo = Mockery::mock(SubscriptionsRepository::class);
        $subRepo->shouldReceive('search')
            ->once()
            ->andReturn(collect([(object) ['user' => $subscriber]]));

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('push down'));

        Log::spy();

        (new OnNewTrip($userRepo, $subRepo))->handle(new TripCreated($trip));

        $expected = 'Ex: '.$trip->to_town.': '.$subscriber->id.' - '.$subscriber->name;

        Log::shouldHaveReceived('info')->with($expected)->once();
    }
}
