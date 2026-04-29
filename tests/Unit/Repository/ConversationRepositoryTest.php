<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\ConversationRepository;
use STS\Repository\FriendsRepository;
use Tests\TestCase;

class ConversationRepositoryTest extends TestCase
{
    public function test_store_and_delete(): void
    {
        $repo = new ConversationRepository;
        $conversation = Conversation::factory()->create(['type' => Conversation::TYPE_PRIVATE_CONVERSATION]);

        $conversation->title = 'Updated title';
        $this->assertTrue($repo->store($conversation));
        $this->assertSame('Updated title', $conversation->fresh()->title);

        $this->assertTrue((bool) $repo->delete($conversation));
        $this->assertTrue($conversation->fresh()->trashed());
    }

    public function test_get_conversation_from_id_without_user_returns_model(): void
    {
        $conversation = Conversation::factory()->create();
        $repo = new ConversationRepository;

        $found = $repo->getConversationFromId($conversation->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->is($conversation));
    }

    public function test_get_conversation_from_id_with_user_requires_membership(): void
    {
        // Mutation intent: keep membership authorization checks and comparison semantics.
        // Kills: 347e5c2ad04e5254, 7f4a6ce0dc5cee48, a583a4a54f8550a2.
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($member->id, ['read' => false]);

        $repo = new ConversationRepository;
        $this->assertNotNull($repo->getConversationFromId($conversation->id, $member));
        $this->assertNull($repo->getConversationFromId($conversation->id, $stranger));
    }

    public function test_get_conversation_from_id_returns_null_for_missing_conversation(): void
    {
        $repo = new ConversationRepository;

        $this->assertNull($repo->getConversationFromId(999999999));
    }

    public function test_get_conversations_by_trip_accepts_trip_instance_or_int_id(): void
    {
        $trip = Trip::factory()->create();
        $a = Conversation::factory()->create(['trip_id' => $trip->id, 'type' => Conversation::TYPE_TRIP_CONVERSATION]);
        $b = Conversation::factory()->create(['trip_id' => $trip->id, 'type' => Conversation::TYPE_TRIP_CONVERSATION]);

        $repo = new ConversationRepository;
        $byModel = $repo->getConversationsByTrip($trip);
        $byId = $repo->getConversationsByTrip($trip->id);

        $this->assertCount(2, $byModel);
        $this->assertCount(2, $byId);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $byModel->pluck('id')->all());
    }

    public function test_get_conversation_by_trip_id_returns_trip_conversation_without_user_filter(): void
    {
        $trip = Trip::factory()->create();
        $conversation = Conversation::factory()->create(['trip_id' => $trip->id]);

        $repo = new ConversationRepository;
        $found = $repo->getConversationByTripId($trip->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($conversation));
    }

    public function test_get_conversation_by_trip_id_requires_membership_when_user_provided(): void
    {
        // Mutation intent: keep user-guard branch and prevent query no-op on membership filtering.
        // Kills: 1e93b573f4f99f69, b5acc029b60ed07a.
        $trip = Trip::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $conversation = Conversation::factory()->create(['trip_id' => $trip->id]);
        $conversation->users()->attach($member->id, ['read' => false]);

        $repo = new ConversationRepository;
        $this->assertNotNull($repo->getConversationByTripId($trip->id, $member));
        $this->assertNull($repo->getConversationByTripId($trip->id, $stranger));
    }

    public function test_get_conversation_by_trip_id_returns_null_when_trip_has_no_conversation(): void
    {
        // Mutation intent: preserve early return when no conversation exists for a trip.
        // Kills: 65cb6b079b80c6c0 (RemoveEarlyReturn variant raised in focused mutation run).
        $trip = Trip::factory()->create();
        $repo = new ConversationRepository;

        $this->assertNull($repo->getConversationByTripId($trip->id));
    }

    public function test_users_add_user_remove_user(): void
    {
        // Mutation intent: verify pivot payload on attach is preserved ("read" => true).
        // Kills: c34eaf14310d5be2, 81b051d962da551e.
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($u1->id, ['read' => true]);

        $repo = new ConversationRepository;
        $repo->addUser($conversation, $u2->id);

        $conversation = $conversation->fresh();
        $this->assertCount(2, $repo->users($conversation));
        $this->assertTrue((bool) $conversation->users()->where('users.id', $u2->id)->first()->pivot->read);

        $repo->removeUser($conversation, $u2);
        $this->assertCount(1, $repo->users($conversation->fresh()));
    }

    public function test_change_and_get_conversation_read_state(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($user->id, ['read' => false]);

        $repo = new ConversationRepository;
        $repo->changeConversationReadState($conversation, $user, true);

        $this->assertTrue((bool) $repo->getConversationReadState($conversation->fresh(), $user));
    }

    public function test_match_user_finds_private_conversation_for_both_users(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conversation = Conversation::factory()->create(['type' => Conversation::TYPE_PRIVATE_CONVERSATION]);
        $conversation->users()->attach($u1->id, ['read' => true]);
        $conversation->users()->attach($u2->id, ['read' => true]);

        $repo = new ConversationRepository;
        $found = $repo->matchUser($u1->id, $u2->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($conversation));
    }

    public function test_match_user_ignores_non_private_and_deleted_conversations(): void
    {
        // Mutation intent: keep private-only, non-deleted constraints in manual join query.
        // Kills: fb1a852cff8663e3, 0c16e14d843483fe, db2999b2618a91ca.
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $tripConversation = Conversation::factory()->create(['type' => Conversation::TYPE_TRIP_CONVERSATION]);
        $tripConversation->users()->attach($u1->id, ['read' => true]);
        $tripConversation->users()->attach($u2->id, ['read' => true]);

        $deletedPrivate = Conversation::factory()->create(['type' => Conversation::TYPE_PRIVATE_CONVERSATION]);
        $deletedPrivate->users()->attach($u1->id, ['read' => true]);
        $deletedPrivate->users()->attach($u2->id, ['read' => true]);
        $deletedPrivate->delete();

        $repo = new ConversationRepository;
        $this->assertNull($repo->matchUser($u1->id, $u2->id));
    }

    public function test_update_trip_id(): void
    {
        $trip = Trip::factory()->create();
        $conversation = Conversation::factory()->create(['trip_id' => null]);

        $repo = new ConversationRepository;
        $updated = $repo->updateTripId($conversation, $trip->id);

        $this->assertSame($trip->id, $updated->fresh()->trip_id);
    }

    public function test_get_conversations_from_user_requires_messages_and_paginates(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($user->id, ['read' => false]);

        Message::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'text' => 'First message',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $c2 = Conversation::factory()->create();
        $c2->users()->attach($user->id, ['read' => false]);
        Message::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $c2->id,
            'text' => 'Second thread',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $conversation->touch();
        $c2->touch();

        $repo = new ConversationRepository;
        $page = $repo->getConversationsFromUser($user, 1, 1);
        $this->assertCount(1, $page);
    }

    public function test_user_list_excludes_self_and_filters_with_search_text(): void
    {
        // Mutation intent: enforce both nested foreach traversal and search/self-exclusion conditions.
        // Kills: 8fa06f6a1fb7d09f, 11abb847856a06ed, e4c6534df6af4cfa, 4b765ce76aaa9ed8, 888e85411dbe27c8.
        $owner = User::factory()->create(['name' => 'Owner Name']);
        $alice = User::factory()->create(['name' => 'Alice Match']);
        $bob = User::factory()->create(['name' => 'Bob Miss']);
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($owner->id, ['read' => false]);
        $conversation->users()->attach($alice->id, ['read' => false]);
        $conversation->users()->attach($bob->id, ['read' => false]);

        Message::query()->create([
            'user_id' => $owner->id,
            'conversation_id' => $conversation->id,
            'text' => 'hello',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $repo = new ConversationRepository;
        $users = $repo->userList($owner, null, 'Alice');

        $this->assertCount(1, $users);
        $this->assertSame($alice->id, $users->first()->id);
    }

    public function test_user_list_null_search_text_includes_every_other_participant(): void
    {
        // Mutation intent: preserve `preg_match("/$search_text/i", ...)` — null builds '//i', which matches any name in PHP.
        $owner = User::factory()->create(['name' => 'Owner NullSearch']);
        $alice = User::factory()->create(['name' => 'Alice NullSearch']);
        $bob = User::factory()->create(['name' => 'Bob NullSearch']);
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($owner->id, ['read' => false]);
        $conversation->users()->attach($alice->id, ['read' => false]);
        $conversation->users()->attach($bob->id, ['read' => false]);

        Message::query()->create([
            'user_id' => $owner->id,
            'conversation_id' => $conversation->id,
            'text' => 'hello-null-search',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $repo = new ConversationRepository;
        $users = $repo->userList($owner, null, null);

        $this->assertCount(2, $users);
        $this->assertEqualsCanonicalizing([$alice->id, $bob->id], $users->pluck('id')->all());
    }

    public function test_users_to_chat_applies_who_and_search_filters_and_excludes_self(): void
    {
        // Mutation intent: keep chat-candidate relation constraints and terminal filters/search.
        // Kills: 923ae30fd029094d, f7d2b59d8b2e231a, fe6d365b386ce4cc, 40233c5b50f76832,
        //        b2e48349c3634d25, 9e7efa4b183e404a, 5714c44fc3ef4640, fb0ef168746086c7,
        //        5494f1022932dc3c, e5486689d57934f9, 8902979f79da0810, a99a6f0833779d83,
        //        bc364170017908f5, 5ec2840d296300e1, f202a89ff2505006, 2fed3ea59a4ffe41,
        //        dee6a17eeeedff27, b7ac732f83368cf0, bf63b36154353e13, 736991285f167c75,
        //        0d19c6b2898b2003, b5672d9eed073752, 46b4eb3f95ab633a, ab6806f4c7906aa3,
        //        a6ef62b9af37fbd3, 9967a653cd968aa7.
        $owner = User::factory()->create(['name' => 'Owner']);
        $friendA = User::factory()->create(['name' => 'Alice Candidate']);
        $friendB = User::factory()->create(['name' => 'Bruno Candidate']);
        (new FriendsRepository)->add($friendA, $owner, User::FRIEND_ACCEPTED);
        (new FriendsRepository)->add($friendB, $owner, User::FRIEND_ACCEPTED);

        $repo = new ConversationRepository;
        $users = $repo->usersToChat($owner->id, $friendA->id, 'Alice');

        $this->assertCount(1, $users);
        $this->assertSame($friendA->id, $users->first()->id);
        $this->assertNotSame($owner->id, $users->first()->id);
        $this->assertTrue($users->first()->relationLoaded('accounts'));
    }

    public function test_users_to_chat_includes_admin_matching_search_without_friend_edge(): void
    {
        // Mutation intent: preserve top-level `orWhere('is_admin', true)` in usersToChat candidate query (~171–176).
        $owner = User::factory()->create(['name' => 'Owner NonAdmin']);
        $needle = 'AdminChatNeedle'.substr(uniqid('', true), 0, 8);
        $admin = User::factory()->create(['name' => $needle.' FullName']);
        $admin->forceFill(['is_admin' => true])->saveQuietly();

        $repo = new ConversationRepository;
        $users = $repo->usersToChat($owner->id, null, 'AdminChatNeedle');

        $this->assertTrue($users->pluck('id')->contains($admin->id));
        $this->assertFalse($users->pluck('id')->contains($owner->id));
    }

    public function test_users_to_chat_includes_driver_with_public_trip_without_friend_edge(): void
    {
        // Mutation intent: exercise `orWhereHas('trips', …)` public-trip disjunct (~176–177).
        $owner = User::factory()->create(['name' => 'Seeker PublicTrip']);
        $needle = 'PubTripDrv'.substr(uniqid('', true), 0, 8);
        $driver = User::factory()->create(['name' => $needle.' Name']);

        Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $repo = new ConversationRepository;
        $users = $repo->usersToChat($owner->id, null, 'PubTripDrv');

        $this->assertTrue($users->pluck('id')->contains($driver->id));
        $this->assertFalse($users->pluck('id')->contains($owner->id));
    }

    public function test_users_to_chat_includes_accepted_passenger_on_seekers_trip_without_friend_edge(): void
    {
        // Mutation intent: exercise trailing `orWhereHas('passenger.trip.user', …)` (~193–195).
        $owner = User::factory()->create(['name' => 'Driver Seeker']);
        $needle = 'PassTripChat'.substr(uniqid('', true), 0, 8);
        $passengerUser = User::factory()->create(['name' => $needle.' Pax']);

        $trip = Trip::factory()->create([
            'user_id' => $owner->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $repo = new ConversationRepository;
        $users = $repo->usersToChat($owner->id, null, 'PassTripChat');

        $this->assertTrue($users->pluck('id')->contains($passengerUser->id));
    }

    public function test_users_to_chat_includes_fof_trip_driver_via_friend_of_friend_without_direct_friendship(): void
    {
        // Mutation intent: exercise FoF nested `trips` block (~178–188): driver FoF trip + `user.friends.friends` path.
        $viewer = User::factory()->create(['name' => 'FoF Viewer Seek']);
        $bridge = User::factory()->create();
        $needle = 'FoFDrvChat'.substr(uniqid('', true), 0, 8);
        $driver = User::factory()->create(['name' => $needle.' Host']);

        $friends = new FriendsRepository;
        $friends->add($driver, $bridge, User::FRIEND_ACCEPTED);
        $friends->add($bridge, $viewer, User::FRIEND_ACCEPTED);

        Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FOF,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $this->assertFalse($viewer->fresh()->friends()->where('users.id', $driver->id)->exists());

        $repo = new ConversationRepository;
        $users = $repo->usersToChat($viewer->id, null, 'FoFDrvChat');

        $this->assertTrue($users->pluck('id')->contains($driver->id));
    }

    public function test_users_to_chat_includes_trip_driver_when_seeker_is_accepted_passenger_via_trips_closure(): void
    {
        // Mutation intent: `orWhereHas('passengerAccepted')` on trip (~189–191); distinguish from outer `passenger.trip.user` branch.
        $viewer = User::factory()->create(['name' => 'Ex Passenger Seek']);
        $needle = 'DrvForPassJoin'.substr(uniqid('', true), 0, 8);
        $driver = User::factory()->create(['name' => $needle.' Captain']);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $viewer->id,
        ]);

        $repo = new ConversationRepository;
        $users = $repo->usersToChat($viewer->id, null, 'DrvForPassJoin');

        $this->assertTrue($users->pluck('id')->contains($driver->id));
    }
}
