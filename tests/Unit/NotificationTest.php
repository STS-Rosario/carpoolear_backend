<?php

namespace Tests\Unit;

use Tests\TestCase;
use STS\Models\User;
use STS\Models\Trip;
use STS\Notifications\DummyNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Services\Notifications\Models\ValueNotification;
use STS\Services\Notifications\Models\DatabaseNotification;

class NotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function testMorph()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $notification = new DatabaseNotification();
        $notification->user_id = $user->id;
        $notification->save();

        $tripValue = new ValueNotification();
        $tripValue->key = 'trip';
        $tripValue->value()->associate($trip);
        $notification->plain_values()->save($tripValue);

        $userValue = new ValueNotification();
        $userValue->key = 'user';
        $userValue->value()->associate($user);
        $notification->plain_values()->save($userValue);

        $fetched = DatabaseNotification::find($notification->id);
        $this->assertNotNull($fetched);
        $this->assertEquals(2, $fetched->plain_values()->count());
    }

    public function testDummyNotification()
    {
        $user = User::factory()->create(['email' => 'marianoabotta@gmail.com']);
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $dummy = new DummyNotification;
        $dummy->setAttribute('dummy', 'dummy');
        $dummy->setAttribute('trip', $trip);

        $dummy->notify($user);

        $notification = DatabaseNotification::first();
        $this->assertNotNull($notification);

        $this->assertEquals($notification->attributes()['trip']->id, $trip->id);

        $notifications = DatabaseNotification::all();
        $first = $notifications->asNotifications()->first();

        $this->assertEquals($first->toString(), 'Dummy Notification dummy');
    }

    public function testNotificationLogic()
    {
        $user = User::factory()->create(['email' => 'marianoabotta@gmail.com']);
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $dummy = new DummyNotification;
        $dummy->setAttribute('dummy', 'dummy');
        $dummy->setAttribute('trip', $trip);

        $dummy->notify($user);

        $manager = \App::make(\STS\Services\Logic\NotificationManager::class);

        $notifications = $manager->getNotifications($user, []);
        $this->assertEquals(count($notifications), 1);

        $notification = $notifications[0];

        $unreadCount = $manager->getUnreadCount($user);
        $this->assertEquals($unreadCount, 1);

        $notifications = $manager->getNotifications($user, ['mark' => true]);
        $unreadCount = $manager->getUnreadCount($user);
        $this->assertEquals($unreadCount, 0);

        $manager->delete($user, $notification['id']);
        $notifications = $manager->getNotifications($user, []);
        $this->assertEquals(count($notifications), 0);
    }
}
