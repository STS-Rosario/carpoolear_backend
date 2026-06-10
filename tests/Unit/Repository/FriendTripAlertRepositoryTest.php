<?php

namespace Tests\Unit\Repository;

use STS\Models\User;
use STS\Repository\FriendTripAlertRepository;
use Tests\TestCase;

class FriendTripAlertRepositoryTest extends TestCase
{
    private function repo(): FriendTripAlertRepository
    {
        return new FriendTripAlertRepository;
    }

    public function test_subscribe_creates_subscription_row(): void
    {
        $subscriber = User::factory()->create();
        $driver = User::factory()->create();

        $this->repo()->subscribe($subscriber, $driver);

        $this->assertDatabaseHas('friend_trip_alert_subscriptions', [
            'user_id' => $subscriber->id,
            'friend_id' => $driver->id,
        ]);
    }

    public function test_unsubscribe_removes_subscription_row(): void
    {
        $subscriber = User::factory()->create();
        $driver = User::factory()->create();
        $this->repo()->subscribe($subscriber, $driver);

        $this->repo()->unsubscribe($subscriber, $driver);

        $this->assertDatabaseMissing('friend_trip_alert_subscriptions', [
            'user_id' => $subscriber->id,
            'friend_id' => $driver->id,
        ]);
    }

    public function test_is_subscribed_returns_true_when_subscribed(): void
    {
        $subscriber = User::factory()->create();
        $driver = User::factory()->create();
        $this->repo()->subscribe($subscriber, $driver);

        $this->assertTrue($this->repo()->isSubscribed($subscriber, $driver));
    }

    public function test_is_subscribed_returns_false_when_not_subscribed(): void
    {
        $subscriber = User::factory()->create();
        $driver = User::factory()->create();

        $this->assertFalse($this->repo()->isSubscribed($subscriber, $driver));
    }

    public function test_toggle_subscribes_when_not_subscribed(): void
    {
        $subscriber = User::factory()->create();
        $driver = User::factory()->create();

        $enabled = $this->repo()->toggle($subscriber, $driver);

        $this->assertTrue($enabled);
        $this->assertTrue($this->repo()->isSubscribed($subscriber, $driver));
    }

    public function test_toggle_unsubscribes_when_subscribed(): void
    {
        $subscriber = User::factory()->create();
        $driver = User::factory()->create();
        $this->repo()->subscribe($subscriber, $driver);

        $enabled = $this->repo()->toggle($subscriber, $driver);

        $this->assertFalse($enabled);
        $this->assertFalse($this->repo()->isSubscribed($subscriber, $driver));
    }

    public function test_get_subscribers_for_driver_returns_subscribed_users(): void
    {
        $driver = User::factory()->create();
        $subscriber1 = User::factory()->create();
        $subscriber2 = User::factory()->create();
        $other = User::factory()->create();

        $this->repo()->subscribe($subscriber1, $driver);
        $this->repo()->subscribe($subscriber2, $driver);
        $this->repo()->subscribe($other, User::factory()->create());

        $subscribers = $this->repo()->getSubscribersForDriver($driver);

        $this->assertCount(2, $subscribers);
        $this->assertTrue($subscribers->contains(fn ($u) => $u->id === $subscriber1->id));
        $this->assertTrue($subscribers->contains(fn ($u) => $u->id === $subscriber2->id));
    }

    public function test_delete_for_users_removes_both_directions(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->repo()->subscribe($user1, $user2);
        $this->repo()->subscribe($user2, $user1);

        $this->repo()->deleteForUsers($user1, $user2);

        $this->assertDatabaseMissing('friend_trip_alert_subscriptions', [
            'user_id' => $user1->id,
            'friend_id' => $user2->id,
        ]);
        $this->assertDatabaseMissing('friend_trip_alert_subscriptions', [
            'user_id' => $user2->id,
            'friend_id' => $user1->id,
        ]);
    }
}
