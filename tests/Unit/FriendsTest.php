<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use STS\Events\Friend\Accept as AcceptEvent;
use STS\Events\Friend\Cancel as CancelEvent;
use STS\Events\Friend\Reject as RejectEvent;
use STS\Events\Friend\Request as RequestEvent;
use STS\Models\User;
use STS\Services\Logic\FriendsManager;
use Tests\TestCase;

class FriendsTest extends TestCase
{
    use DatabaseTransactions;

    private FriendsManager $friends;

    protected function setUp(): void
    {
        parent::setUp();
        $this->friends = $this->app->make(FriendsManager::class);
    }

    public function test_request_accept_creates_mutual_friendship_and_dispatches_events(): void
    {
        Event::fake([RequestEvent::class, AcceptEvent::class]);
        $users = User::factory()->count(2)->create();

        $this->assertTrue($this->friends->request($users[0], $users[1]));
        $this->assertTrue($this->friends->accept($users[1], $users[0]));

        Event::assertDispatched(RequestEvent::class);
        Event::assertDispatched(AcceptEvent::class);
        $this->assertTrue($this->friends->areFriend($users[0], $users[1]));
        $this->assertTrue($this->friends->areFriend($users[1], $users[0]));
    }

    public function test_request_reject_keeps_users_as_non_friends_and_dispatches_reject_event(): void
    {
        Event::fake([RequestEvent::class, RejectEvent::class]);
        $users = User::factory()->count(2)->create();

        $this->assertTrue($this->friends->request($users[0], $users[1]));
        $this->assertTrue($this->friends->reject($users[1], $users[0]));

        Event::assertDispatched(RequestEvent::class);
        Event::assertDispatched(RejectEvent::class);
        $this->assertFalse($this->friends->areFriend($users[0], $users[1]));
    }

    public function test_delete_friends_removes_edge_and_dispatches_cancel_event(): void
    {
        Event::fake([CancelEvent::class]);
        $users = User::factory()->count(2)->create();

        $this->assertTrue($this->friends->make($users[0], $users[1]));
        $this->assertCount(1, $this->friends->getFriends($users[0]));

        $this->assertTrue($this->friends->delete($users[1], $users[0]));

        Event::assertDispatched(CancelEvent::class);
        $this->assertFalse($this->friends->areFriend($users[0], $users[1]));
        $this->assertCount(0, $this->friends->getFriends($users[0]));
    }

    public function test_get_friends_returns_expected_counts_for_each_side(): void
    {
        $users = User::factory()->count(3)->create();

        $this->assertTrue($this->friends->make($users[0], $users[1]));
        $this->assertTrue($this->friends->make($users[0], $users[2]));

        $this->assertCount(2, $this->friends->getFriends($users[0]));
        $this->assertCount(1, $this->friends->getFriends($users[1]));
        $this->assertCount(1, $this->friends->getFriends($users[2]));
    }

    public function test_user_relationship_collections_and_pending_requests(): void
    {
        $users = User::factory()->count(3)->create();

        $this->assertTrue($this->friends->make($users[0], $users[1]));
        $this->assertTrue($this->friends->request($users[0], $users[2]));

        $this->assertCount(1, $users[0]->fresh()->friends);
        $this->assertCount(2, $users[0]->fresh()->allFriends);
        $this->assertCount(1, $this->friends->getPendings($users[2]));
    }

    public function test_friends_of_friends_flag_controls_second_degree_lookup(): void
    {
        $users = User::factory()->count(3)->create();

        $this->assertTrue($this->friends->make($users[0], $users[1]));
        $this->assertTrue($this->friends->make($users[1], $users[2]));

        $this->assertFalse($this->friends->areFriend($users[0], $users[2]));
        $this->assertTrue($this->friends->areFriend($users[0], $users[2], true));
        $this->assertTrue($this->friends->areFriend($users[0], $users[1], true));
    }

    /*
    public function testApiFriends()
    {

        $this->refreshApplication();
        $users = \STS\Models\User::factory()->count(3)->create();
        $t1 = \JWTAuth::attempt(['email' => $users[0]->email, 'password' => '123456']);
        $t2 = \JWTAuth::attempt(['email' => $users[1]->email, 'password' => '123456']);
        $t3 = \JWTAuth::attempt(['email' => $users[2]->email, 'password' => '123456']);

        $response = $this->call('POST', 'api/friends/request/'. $users[1]->id . '?token=' . $t1);

        $this->assertTrue($response->status() == 200);
    }
    */
}
