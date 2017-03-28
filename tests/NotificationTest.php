<?php

use STS\User;
use STS\Entities\Trip;
use STS\Entities\TripPoint;

use STS\Services\Notifications\Models\DatabaseNotification;
use STS\Services\Notifications\Models\ValueNotification;

use Illuminate\Foundation\Testing\DatabaseTransactions;

use STS\Notifications\DummyNotification;

class NotificationTest extends TestCase
{
    use DatabaseTransactions;
 

    public function testMorph()
    {
        $t = factory(Trip::class)->create();
        $u1 = factory(STS\User::class)->create();
        $n = new DatabaseNotification();
        $n->user_id = $u1->id;
        $n->save();
        
        $v = new ValueNotification();
        $v->key = "trip";
        $v->value()->associate($t);
        $n->plain_values()->save($v);

        $v = new ValueNotification();
        $v->key = "user";
        $v->value()->associate($u1);
        $n->plain_values()->save($v);
        
        $nn = DatabaseNotification::find($n->id);
        //console_log($nn->attributes());
    }

    public function testDummyNotification()
    {
        $user = factory(STS\User::class)->create(["email" => "marianoabotta@gmail.com"]);
        $trip = factory(Trip::class)->create();

        $dummy = new DummyNotification;
        $dummy->setAttribute("dummy", "dummy");
        $dummy->setAttribute("trip", $trip);

        $dummy->notify($user);

        $noti = DatabaseNotification::first();
        $this->assertNotNull($noti);

        $this->assertEquals($noti->attributes()["trip"]->id, $trip->id);

        $notifications = DatabaseNotification::all();
        $first = $notifications->asNotifications()->first();  

        $this->assertEquals($first->toString(), "Dummy Notification dummy"); 
    }
}
