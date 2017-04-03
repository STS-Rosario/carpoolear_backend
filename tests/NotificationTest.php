<?php

use STS\User;
use STS\Entities\Trip;
use STS\Notifications\DummyNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Services\Notifications\Models\ValueNotification;
use STS\Services\Notifications\Models\DatabaseNotification;

class NotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function testMorph()
    {
        $u1 = factory(STS\User::class)->create();
        $t = factory(Trip::class)->create(['user_id' => $u1->id ]);
        $n = new DatabaseNotification();
        $n->user_id = $u1->id;
        $n->save();

        $v = new ValueNotification();
        $v->key = 'trip';
        $v->value()->associate($t);
        $n->plain_values()->save($v);

        $v = new ValueNotification();
        $v->key = 'user';
        $v->value()->associate($u1);
        $n->plain_values()->save($v);

        $nn = DatabaseNotification::find($n->id);
        //console_log($nn->attributes());
    }

    public function testDummyNotification()
    {
        $user = factory(STS\User::class)->create(['email' => 'marianoabotta@gmail.com']);
        $trip = factory(Trip::class)->create(['user_id' => $user->id ]);

        $dummy = new DummyNotification;
        $dummy->setAttribute('dummy', 'dummy');
        $dummy->setAttribute('trip', $trip);

        $dummy->notify($user);

        $noti = DatabaseNotification::first();
        $this->assertNotNull($noti);

        $this->assertEquals($noti->attributes()['trip']->id, $trip->id);

        $notifications = DatabaseNotification::all();
        $first = $notifications->asNotifications()->first();

        $this->assertEquals($first->toString(), 'Dummy Notification dummy');
    }

    public function testNotificationLogic()
    {
        $user = factory(STS\User::class)->create(['email' => 'marianoabotta@gmail.com']);
        $trip = factory(Trip::class)->create(['user_id' => $user->id ]);

        $dummy = new DummyNotification;
        $dummy->setAttribute('dummy', 'dummy');
        $dummy->setAttribute('trip', $trip);

        $dummy->notify($user);

        $manager = \App::make('\STS\Contracts\Logic\INotification');

        $datos = $manager->getNotifications($user, []);
        $this->assertEquals(count($datos), 1);

        $noti = $datos[0];

        $count = $manager->getUnreadCount($user);
        $this->assertEquals($count, 1);

        $datos = $manager->getNotifications($user, ['mark' => true]);
        $count = $manager->getUnreadCount($user);
        $this->assertEquals($count, 0);

        $manager->delete($user, $noti['id']);
        $datos = $manager->getNotifications($user, []);
        $this->assertEquals(count($datos), 0);
    }
}
