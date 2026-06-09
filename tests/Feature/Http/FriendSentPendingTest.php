<?php

namespace Tests\Feature\Http;

use STS\Models\User;
use STS\Repository\FriendsRepository;
use STS\Repository\FriendTripAlertRepository;
use STS\Services\Logic\FriendsManager;
use Tests\TestCase;

class FriendSentPendingTest extends TestCase
{
    private function makeFriends(User $a, User $b): void
    {
        (new FriendsManager(new FriendsRepository, new FriendTripAlertRepository))->request($a, $b);
    }

    public function test_sent_pendings_lists_outgoing_friend_requests(): void
    {
        $actor = User::factory()->create();
        $sentTo = User::factory()->create();
        $incomingFrom = User::factory()->create();
        $this->makeFriends($actor, $sentTo);
        $this->makeFriends($incomingFrom, $actor);

        $this->actingAs($actor, 'api')
            ->getJson('/api/friends/sent-pendings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sentTo->id);
    }

    public function test_cancel_request_removes_outgoing_friend_request(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $this->makeFriends($actor, $target);

        $this->actingAs($actor, 'api')
            ->postJson("/api/friends/cancel-request/{$target->id}")
            ->assertOk();

        $this->actingAs($actor, 'api')
            ->getJson('/api/friends/sent-pendings')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
