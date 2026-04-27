<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use STS\Models\NodeGeo;
use STS\Models\Subscription;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\FriendsRepository;
use STS\Repository\SubscriptionsRepository;
use Tests\TestCase;

class SubscriptionsRepositoryTest extends TestCase
{
    private function repo(): SubscriptionsRepository
    {
        return new SubscriptionsRepository;
    }

    private function makeNode(array $overrides = []): NodeGeo
    {
        $node = new NodeGeo;
        $node->forceFill(array_merge([
            'name' => 'SubN'.substr(uniqid('', true), 0, 6),
            'lat' => -34.5,
            'lng' => -58.5,
            'type' => 'city',
            'state' => 'BA',
            'country' => 'AR',
            'importance' => 1,
        ], $overrides));
        $node->save();

        return $node->fresh();
    }

    public function test_create_update_show_delete_round_trip(): void
    {
        $user = User::factory()->create();
        $sub = new Subscription([
            'user_id' => $user->id,
            'state' => true,
            'trip_date' => Carbon::parse('2025-03-01 10:00:00'),
            'is_passenger' => false,
        ]);

        $this->assertTrue($this->repo()->create($sub));
        $this->assertNotNull($sub->id);

        $sub->state = false;
        $this->assertTrue($this->repo()->update($sub));
        $this->assertFalse((bool) $this->repo()->show($sub->id)->state);

        $this->assertTrue((bool) $this->repo()->delete($sub));
        $this->assertNull(Subscription::query()->find($sub->id));
    }

    public function test_list_returns_all_or_filters_by_state(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'state' => true]);
        Subscription::factory()->create(['user_id' => $user->id, 'state' => false]);

        $all = $this->repo()->list($user->fresh(), null);
        $this->assertCount(2, $all);

        $active = $this->repo()->list($user->fresh(), true);
        $this->assertCount(1, $active);
        $this->assertTrue((bool) $active->first()->state);
    }

    public function test_search_public_trip_matches_path_state_and_passenger_flag(): void
    {
        $n1 = $this->makeNode(['lat' => -34.0, 'lng' => -58.0]);
        $n2 = $this->makeNode(['lat' => -35.0, 'lng' => -59.0]);
        $subscriber = User::factory()->create();
        $path = '.'.$n1->id.'.'.$n2->id.'.';

        $match = Subscription::factory()->create([
            'user_id' => $subscriber->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'state' => true,
            'is_passenger' => false,
            'trip_date' => null,
        ]);
        Subscription::factory()->create([
            'user_id' => $subscriber->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'state' => true,
            'is_passenger' => true,
            'trip_date' => null,
        ]);

        $trip = Trip::factory()->create([
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'path' => $path,
            'trip_date' => Carbon::now()->addDays(2),
            'is_passenger' => false,
        ]);

        $viewer = User::factory()->create();
        $rows = $this->repo()->search($viewer, $trip);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->is($match));
    }

    public function test_search_friends_trip_only_includes_subscriptions_from_friends(): void
    {
        $n1 = $this->makeNode();
        $n2 = $this->makeNode();
        $path = '.'.$n1->id.'.'.$n2->id.'.';

        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        (new FriendsRepository)->add($viewer, $friend, User::FRIEND_ACCEPTED);

        $friendSub = Subscription::factory()->create([
            'user_id' => $friend->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'state' => true,
            'is_passenger' => false,
            'trip_date' => null,
        ]);
        Subscription::factory()->create([
            'user_id' => $stranger->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'state' => true,
            'is_passenger' => false,
            'trip_date' => null,
        ]);

        $trip = Trip::factory()->create([
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
            'path' => $path,
            'trip_date' => Carbon::now()->addDays(2),
            'is_passenger' => false,
        ]);

        $rows = $this->repo()->search($viewer, $trip);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->is($friendSub));
    }

    public function test_get_potential_node_returns_node_inside_bounding_box(): void
    {
        $n1 = $this->makeNode(['lat' => -30.0, 'lng' => -55.0]);
        $n2 = $this->makeNode(['lat' => -31.0, 'lng' => -56.0]);
        $inside = $this->makeNode(['lat' => -30.5, 'lng' => -55.5, 'name' => 'InsideSubBox']);

        $found = $this->repo()->getPotentialNode($n1, $n2);

        $this->assertNotNull($found);
        $this->assertContains($found->id, [$n1->id, $n2->id, $inside->id]);
    }
}
