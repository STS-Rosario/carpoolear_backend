<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Mail;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\DummyNotification;
use STS\Services\Logic\NotificationManager;
use STS\Services\Notifications\Models\DatabaseNotification;
use STS\Services\Notifications\Models\ValueNotification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    private NotificationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(NotificationManager::class);
        Mail::fake();
    }

    private function sendDummy(User $user, Trip $trip, string $value = 'dummy'): void
    {
        $dummy = new DummyNotification;
        $dummy->setAttribute('dummy', $value);
        $dummy->setAttribute('trip', $trip);
        $dummy->notify($user);
    }

    public function test_morph_values_persist_and_resolve_related_models(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $notification = new DatabaseNotification;
        $notification->user_id = $user->id;
        $notification->save();

        $tripValue = new ValueNotification;
        $tripValue->key = 'trip';
        $tripValue->value()->associate($trip);
        $notification->plain_values()->save($tripValue);

        $userValue = new ValueNotification;
        $userValue->key = 'user';
        $userValue->value()->associate($user);
        $notification->plain_values()->save($userValue);

        $fetched = DatabaseNotification::find($notification->id);
        $this->assertNotNull($fetched);
        $this->assertSame(2, $fetched->plain_values()->count());
        $this->assertSame($trip->id, $fetched->attributes()['trip']->id);
        $this->assertSame($user->id, $fetched->attributes()['user']->id);
    }

    public function test_dummy_notification_persists_trip_attribute_and_string_representation(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->sendDummy($user, $trip, 'dummy');

        $notification = DatabaseNotification::query()->first();
        $this->assertNotNull($notification);
        $this->assertSame($trip->id, $notification->attributes()['trip']->id);

        $notifications = DatabaseNotification::all();
        $first = $notifications->asNotifications()->first();
        $this->assertSame('Dummy Notification dummy', $first->toString());
    }

    public function test_notification_manager_flow_read_mark_and_delete(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->sendDummy($user, $trip, 'dummy');

        $notifications = $this->manager->getNotifications($user, []);
        $this->assertCount(1, $notifications);
        $notification = $notifications[0];
        $this->assertSame('Dummy Notification dummy', $notification['text']);
        $this->assertFalse($notification['readed']);

        $this->assertSame(1, $this->manager->getUnreadCount($user));
        $this->manager->getNotifications($user, ['mark' => true]);
        $this->assertSame(0, $this->manager->getUnreadCount($user));

        $this->manager->delete($user, $notification['id']);
        $this->assertCount(0, $this->manager->getNotifications($user, []));
    }
}
