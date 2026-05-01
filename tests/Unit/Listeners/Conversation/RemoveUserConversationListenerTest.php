<?php

namespace Tests\Unit\Listeners\Conversation;

use Mockery;
use STS\Events\Passenger\Cancel as PassengerCanceled;
use STS\Listeners\Conversation\removeUserConversation;
use STS\Models\Conversation;
use STS\Models\User;
use STS\Repository\ConversationRepository;
use Tests\TestCase;

class RemoveUserConversationListenerTest extends TestCase
{
    public function test_handle_does_not_call_repository_when_trip_has_no_conversation(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();

        $trip = new \stdClass;
        $trip->user_id = $driver->id;
        $trip->conversation = null;

        $repo = Mockery::mock(ConversationRepository::class);
        $repo->shouldNotReceive('removeUser');

        (new removeUserConversation($repo))->handle(
            new PassengerCanceled($trip, $driver, $passenger, 0)
        );
    }

    public function test_handle_removes_passenger_when_cancel_initiated_by_trip_owner(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $conversation = Mockery::mock(Conversation::class);

        $trip = new \stdClass;
        $trip->user_id = $driver->id;
        $trip->conversation = $conversation;

        $repo = Mockery::mock(ConversationRepository::class);
        $repo->shouldReceive('removeUser')
            ->once()
            ->with(
                $conversation,
                Mockery::on(fn (User $user) => $user->is($passenger))
            );

        (new removeUserConversation($repo))->handle(
            new PassengerCanceled($trip, $driver, $passenger, 0)
        );
    }

    public function test_handle_removes_cancel_initiator_when_they_are_not_the_trip_owner(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $moderator = User::factory()->create();
        $conversation = Mockery::mock(Conversation::class);

        $trip = new \stdClass;
        $trip->user_id = $driver->id;
        $trip->conversation = $conversation;

        $repo = Mockery::mock(ConversationRepository::class);
        $repo->shouldReceive('removeUser')
            ->once()
            ->with(
                $conversation,
                Mockery::on(fn (User $user) => $user->is($moderator))
            );

        (new removeUserConversation($repo))->handle(
            new PassengerCanceled($trip, $moderator, $passenger, 0)
        );
    }

    public function test_handle_uses_loose_equality_so_numeric_string_trip_owner_id_matches_driver(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $conversation = Mockery::mock(Conversation::class);

        $trip = new \stdClass;
        $trip->user_id = (string) $driver->id;
        $trip->conversation = $conversation;

        $repo = Mockery::mock(ConversationRepository::class);
        $repo->shouldReceive('removeUser')
            ->once()
            ->with(
                $conversation,
                Mockery::on(fn (User $user) => $user->is($passenger))
            );

        (new removeUserConversation($repo))->handle(
            new PassengerCanceled($trip, $driver, $passenger, 0)
        );
    }
}
