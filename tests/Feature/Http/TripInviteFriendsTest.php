<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Mockery;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\FriendTripInviteNotification;
use STS\Repository\FriendsRepository;
use STS\Repository\FriendTripAlertRepository;
use STS\Services\Logic\FriendsManager;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class TripInviteFriendsTest extends TestCase
{
    private function makeFriends(User $a, User $b): void
    {
        (new FriendsManager(new FriendsRepository, new FriendTripAlertRepository))->make($a, $b);
    }

    public function test_invite_friends_notifies_accepted_friends_only(): void
    {
        $driver = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $this->makeFriends($driver, $friend);

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
            ->withArgs(function ($notification, $users, $channel) use ($trip, $friend, $driver) {
                if (! $notification instanceof FriendTripInviteNotification) {
                    return false;
                }

                return $notification->getAttribute('trip')->is($trip)
                    && $notification->getAttribute('driver')->is($driver)
                    && $users instanceof User
                    && $users->is($friend)
                    && is_string($channel);
            });

        $this->actingAs($driver, 'api')
            ->postJson("/api/trips/{$trip->id}/invite-friends", [
                'friend_ids' => [$friend->id, $stranger->id],
            ])
            ->assertOk()
            ->assertJsonPath('invited_count', 1);
    }

    public function test_invite_friends_denies_non_owner(): void
    {
        $driver = User::factory()->create();
        $other = User::factory()->create();
        $friend = User::factory()->create();
        $this->makeFriends($driver, $friend);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $this->actingAs($other, 'api')
            ->postJson("/api/trips/{$trip->id}/invite-friends", [
                'friend_ids' => [$friend->id],
            ])
            ->assertStatus(422);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
