<?php

namespace Tests\Unit\Services\Logic;

use Illuminate\Support\Facades\Event;
use STS\Events\Friend\Accept as AcceptEvent;
use STS\Events\Friend\Cancel as CancelEvent;
use STS\Events\Friend\Reject as RejectEvent;
use STS\Events\Friend\Request as RequestEvent;
use STS\Models\User;
use STS\Repository\FriendsRepository;
use STS\Services\Logic\FriendsManager;
use Tests\TestCase;

class FriendsManagerTest extends TestCase
{
    private function manager(): FriendsManager
    {
        return new FriendsManager(new FriendsRepository);
    }

    public function test_are_friend_false_without_edge(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->assertFalse($this->manager()->areFriend($a, $b));
        $this->assertFalse($this->manager()->areFriend($a, $b, true));
    }

    public function test_are_friend_true_when_accepted(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->manager()->make($a, $b);

        $this->assertTrue($this->manager()->areFriend($a, $b));
        $this->assertTrue($this->manager()->areFriend($b, $a));
    }

    public function test_are_friend_with_friend_of_friends_uses_closest_link(): void
    {
        $a = User::factory()->create();
        $m = User::factory()->create();
        $b = User::factory()->create();
        $this->manager()->make($a, $m);
        $this->manager()->make($m, $b);

        $this->assertFalse($this->manager()->areFriend($a, $b));
        $this->assertTrue($this->manager()->areFriend($a, $b, true));
    }

    public function test_request_dispatches_event_and_creates_pending_row(): void
    {
        Event::fake();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->assertTrue($this->manager()->request($alice, $bob));

        Event::assertDispatched(RequestEvent::class);
        $this->assertTrue($this->manager()->getPendings($bob)->pluck('id')->contains($alice->id));
    }

    public function test_request_fails_when_already_accepted_friends(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->manager()->make($a, $b);

        $manager = $this->manager();
        $this->assertNull($manager->request($a, $b));
        $this->assertSame('Operación inválida', $manager->getErrors()['error']);
    }

    public function test_accept_after_request_dispatches_accept_event(): void
    {
        Event::fake([RequestEvent::class, AcceptEvent::class]);
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->manager()->request($alice, $bob);

        $this->assertTrue($this->manager()->accept($bob, $alice));

        Event::assertDispatched(AcceptEvent::class);
        $this->assertTrue($this->manager()->areFriend($alice, $bob));
    }

    public function test_accept_fails_without_pending_request(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->accept($a, $b));
        $this->assertSame('Operación inválida', $manager->getErrors()['error']);
    }

    public function test_reject_after_request_dispatches_reject_event(): void
    {
        Event::fake();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->manager()->request($alice, $bob);

        $this->assertTrue($this->manager()->reject($bob, $alice));

        Event::assertDispatched(RejectEvent::class);
        $this->assertFalse($this->manager()->areFriend($alice, $bob));
    }

    public function test_delete_removes_accepted_friendship_and_dispatches_cancel(): void
    {
        Event::fake();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->manager()->make($a, $b);

        $this->assertTrue($this->manager()->delete($a, $b));

        Event::assertDispatched(CancelEvent::class);
        $this->assertFalse($this->manager()->areFriend($a, $b));
    }

    public function test_delete_fails_when_not_friends(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->delete($a, $b));
        $this->assertSame('Operación inválida', $manager->getErrors()['error']);
    }

    public function test_get_friends_delegates_to_repository(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->manager()->make($a, $b);

        $friends = $this->manager()->getFriends($a, []);

        $this->assertTrue($friends->pluck('id')->contains($b->id));
    }
}
