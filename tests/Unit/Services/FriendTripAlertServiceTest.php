<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use Mockery;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\FriendCreatedTripNotification;
use STS\Repository\FriendsRepository;
use STS\Repository\FriendTripAlertRepository;
use STS\Services\FriendTripAlertService;
use STS\Services\Logic\TripsManager;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class FriendTripAlertServiceTest extends TestCase
{
    private function friendsRepo(): FriendsRepository
    {
        return new FriendsRepository;
    }

    private function service(?FriendTripAlertRepository $alertRepo = null, ?TripsManager $tripsManager = null): FriendTripAlertService
    {
        return new FriendTripAlertService(
            $alertRepo ?? new FriendTripAlertRepository,
            $tripsManager ?? app(TripsManager::class)
        );
    }

    public function test_notify_if_visible_skips_when_alert_already_sent(): void
    {
        $driver = User::factory()->create();
        $subscriber = User::factory()->create();
        $this->friendsRepo()->add($driver, $subscriber, User::FRIEND_ACCEPTED);
        $this->friendsRepo()->add($subscriber, $driver, User::FRIEND_ACCEPTED);

        $alertRepo = new FriendTripAlertRepository;
        $alertRepo->subscribe($subscriber, $driver);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
            'friend_trip_alert_sent_at' => Carbon::now(),
        ]);

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $this->service($alertRepo)->notifyIfVisible($trip->fresh());
    }

    public function test_notify_if_visible_skips_subscriber_who_cannot_see_trip(): void
    {
        $driver = User::factory()->create();
        $subscriber = User::factory()->create();
        $this->friendsRepo()->add($driver, $subscriber, User::FRIEND_ACCEPTED);
        $this->friendsRepo()->add($subscriber, $driver, User::FRIEND_ACCEPTED);

        $alertRepo = new FriendTripAlertRepository;
        $alertRepo->subscribe($subscriber, $driver);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
            'trip_date' => Carbon::now()->addDay(),
            'needs_sellado' => true,
            'state' => Trip::STATE_AWAITING_PAYMENT,
        ]);

        $tripsManager = Mockery::mock(TripsManager::class);
        $tripsManager->shouldReceive('userCanSeeTrip')
            ->once()
            ->with(
                Mockery::on(fn (User $u) => $u->id === $subscriber->id),
                Mockery::on(fn (Trip $t) => $t->id === $trip->id)
            )
            ->andReturn(false);

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $this->service($alertRepo, $tripsManager)->notifyIfVisible($trip->fresh());

        $this->assertNull($trip->fresh()->friend_trip_alert_sent_at);
    }

    public function test_notify_if_visible_notifies_subscribers_and_marks_trip_sent(): void
    {
        $driver = User::factory()->create(['name' => 'Driver Name']);
        $subscriber = User::factory()->create();
        $this->friendsRepo()->add($driver, $subscriber, User::FRIEND_ACCEPTED);
        $this->friendsRepo()->add($subscriber, $driver, User::FRIEND_ACCEPTED);

        $alertRepo = new FriendTripAlertRepository;
        $alertRepo->subscribe($subscriber, $driver);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'from_town' => 'Rosario',
            'to_town' => 'Buenos Aires',
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(function ($notification, $users, $channel) use ($trip, $subscriber, $driver) {
                if (! $notification instanceof FriendCreatedTripNotification) {
                    return false;
                }

                return $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('driver')->is($driver)
                    && $users instanceof User
                    && $users->is($subscriber)
                    && is_string($channel);
            });

        $this->service($alertRepo)->notifyIfVisible($trip->fresh());

        $this->assertNotNull($trip->fresh()->friend_trip_alert_sent_at);
    }

    public function test_notify_if_visible_skips_when_no_subscribers(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $this->service()->notifyIfVisible($trip->fresh());

        $this->assertNull($trip->fresh()->friend_trip_alert_sent_at);
    }
}
