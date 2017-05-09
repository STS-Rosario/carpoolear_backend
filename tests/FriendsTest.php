<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

class FriendsTest extends TestCase
{
    use DatabaseTransactions;

    public function testRequestAccept()
    {
        $friends = \App::make('\STS\Contracts\Logic\Friends');

        $users = factory(STS\User::class, 3)->create();

        $ret = $friends->request($users[0], $users[1]);
        $this->assertTrue($ret);

        $ret = $friends->accept($users[1], $users[0]);
        $this->assertTrue($ret);

        $ret = $friends->areFriend($users[0], $users[1]);
        $this->assertTrue($ret);
    }

    public function testRequestReject()
    {
        $friends = \App::make('\STS\Contracts\Logic\Friends');

        $users = factory(STS\User::class, 3)->create();

        $ret = $friends->request($users[0], $users[1]);
        $this->assertTrue($ret);

        $ret = $friends->reject($users[1], $users[0]);
        $this->assertTrue($ret);

        $ret = $friends->areFriend($users[0], $users[1]);
        $this->assertFalse($ret);
    }

    public function testDeleteFriends()
    {
        $friends = \App::make('\STS\Contracts\Logic\Friends');

        $users = factory(STS\User::class, 3)->create();

        $ret = $friends->make($users[0], $users[1]);
        $this->assertTrue($ret);

        $this->assertTrue($friends->getFriends($users[0])->count() > 0);

        $ret = $friends->delete($users[1], $users[0]);
        $this->assertTrue($ret);

        $ret = $friends->areFriend($users[0], $users[1]);
        $this->assertFalse($ret);
    }

    public function testGetFriends()
    {
        $friends = \App::make('\STS\Contracts\Logic\Friends');

        $users = factory(STS\User::class, 3)->create();

        $ret = $friends->make($users[0], $users[1]);
        $ret = $friends->make($users[0], $users[2]);
        $this->assertTrue($friends->getFriends($users[0])->count() == 2);

        $this->assertTrue($friends->getFriends($users[1])->count() == 1);
    }

    public function testUserRelationship()
    {
        $friends = \App::make('\STS\Contracts\Logic\Friends');

        $users = factory(STS\User::class, 3)->create();

        $ret = $friends->make($users[0], $users[1]);
        $ret = $friends->request($users[0], $users[2]); 

        $this->assertTrue($users[0]->friends->count() == 1);

        $this->assertTrue($users[0]->allFriends->count() == 2); 

        $this->assertTrue($friends->getPendings($users[2])->count() == 1);
    }

    public function testFriendsOfFriends()
    {
        $friends = \App::make('\STS\Contracts\Logic\Friends');

        $users = factory(STS\User::class, 3)->create();

        $ret = $friends->make($users[0], $users[1]);
        $ret = $friends->make($users[1], $users[2]);

        $ret = $friends->areFriend($users[0], $users[2], true);
        $this->assertTrue($ret);

        $ret = $friends->areFriend($users[0], $users[1], true);
        $this->assertTrue($ret);
    }

    /*
    public function testApiFriends()
    {

        $this->refreshApplication();
        $users = factory(STS\User::class, 3)->create();
        $t1 = \JWTAuth::attempt(['email' => $users[0]->email, 'password' => '123456']);
        $t2 = \JWTAuth::attempt(['email' => $users[1]->email, 'password' => '123456']);
        $t3 = \JWTAuth::attempt(['email' => $users[2]->email, 'password' => '123456']);

        $response = $this->call('POST', 'api/friends/request/'. $users[1]->id . '?token=' . $t1);

        $this->assertTrue($response->status() == 200);
    }
    */
}
