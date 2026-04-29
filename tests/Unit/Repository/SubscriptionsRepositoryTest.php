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

    private function trig(float $lat, float $lng): array
    {
        $latd = deg2rad($lat);
        $lngd = deg2rad($lng);

        return [
            'sin_lat' => sin($latd),
            'sin_lng' => sin($lngd),
            'cos_lat' => cos($latd),
            'cos_lng' => cos($lngd),
        ];
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

    public function test_list_treats_zero_string_as_state_filter_and_not_null(): void
    {
        // Mutation intent: keep null-check behavior distinct from strict-identity changes.
        // Kills: f33f83c629152a9b (Line 35 EqualToIdentical).
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'state' => true]);
        $inactive = Subscription::factory()->create(['user_id' => $user->id, 'state' => false]);

        $rows = $this->repo()->list($user->fresh(), '0');

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->is($inactive));
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

    public function test_search_public_applies_date_window_state_and_recent_creation_filters(): void
    {
        // Mutation intent: preserve date-window bounds, active-state filter and recency gate.
        // Kills: 4b6a25e18d53c80a, 39c82e8a6e59b89c, bfcdfc59b0de38cd, fead23a67492db61,
        //        5bd3e5b021c99599, 67480eeb42185937, ceaea2c03b3fff6c.
        $n1 = $this->makeNode(['lat' => -34.0, 'lng' => -58.0]);
        $n2 = $this->makeNode(['lat' => -35.0, 'lng' => -59.0]);
        $subscriber = User::factory()->create();
        $tripDate = Carbon::now('America/Argentina/Buenos_Aires')->addDay()->setTime(15, 0);
        $path = '.'.$n1->id.'.'.$n2->id.'.';

        $matching = Subscription::factory()->create([
            'user_id' => $subscriber->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'trip_date' => $tripDate->copy()->setTime(10, 0),
            'state' => true,
            'is_passenger' => false,
            'created_at' => Carbon::now()->subMonths(2),
        ]);

        // Same-day upper bound check.
        Subscription::factory()->create([
            'user_id' => $subscriber->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'trip_date' => $tripDate->copy()->addDay(),
            'state' => true,
            'is_passenger' => false,
            'created_at' => Carbon::now()->subMonths(2),
        ]);

        // State filter check.
        Subscription::factory()->create([
            'user_id' => $subscriber->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'trip_date' => $tripDate->copy()->setTime(11, 0),
            'state' => false,
            'is_passenger' => false,
            'created_at' => Carbon::now()->subMonths(2),
        ]);

        // Recent creation filter check.
        Subscription::factory()->create([
            'user_id' => $subscriber->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'trip_date' => $tripDate->copy()->setTime(12, 0),
            'state' => true,
            'is_passenger' => false,
            'created_at' => Carbon::now()->subMonths(7),
        ]);

        $trip = Trip::factory()->create([
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'path' => $path,
            'trip_date' => $tripDate,
            'is_passenger' => false,
        ]);

        $rows = $this->repo()->search(User::factory()->create(), $trip);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->is($matching));
    }

    public function test_search_public_uses_now_when_trip_day_is_today(): void
    {
        // Mutation intent: keep lower date boundary clamped to "now" when searching today.
        // Kills: d67ef5e7ca5b898b (Line 49 IfNegated).
        $n1 = $this->makeNode(['lat' => -34.0, 'lng' => -58.0]);
        $n2 = $this->makeNode(['lat' => -35.0, 'lng' => -59.0]);
        $subscriber = User::factory()->create();
        $path = '.'.$n1->id.'.'.$n2->id.'.';
        $now = Carbon::now('America/Argentina/Buenos_Aires');

        Subscription::factory()->create([
            'user_id' => $subscriber->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'trip_date' => $now->copy()->subMinutes(5),
            'state' => true,
            'is_passenger' => false,
            'created_at' => Carbon::now()->subDay(),
        ]);

        $future = Subscription::factory()->create([
            'user_id' => $subscriber->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'trip_date' => $now->copy()->addMinutes(20),
            'state' => true,
            'is_passenger' => false,
            'created_at' => Carbon::now()->subDay(),
        ]);

        $trip = Trip::factory()->create([
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'path' => $path,
            'trip_date' => $now->copy()->setTime(23, 0),
            'is_passenger' => false,
        ]);

        $rows = $this->repo()->search(User::factory()->create(), $trip);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->is($future));
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

    public function test_search_fof_trip_includes_friend_and_relative_friend_subscriptions(): void
    {
        // Mutation intent: enforce both whereIn/orWhereIn branches for FoF visibility.
        // Kills: dfa3484f97c01a8b, 415c311b42d7720d, 0d971ac0fb11da7f, 7d76960a48011f57.
        $n1 = $this->makeNode();
        $n2 = $this->makeNode();
        $path = '.'.$n1->id.'.'.$n2->id.'.';

        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $relativeFriend = User::factory()->create();
        $bridge = User::factory()->create();
        $stranger = User::factory()->create();
        $friendsRepo = new FriendsRepository;
        $friendsRepo->add($viewer, $friend, User::FRIEND_ACCEPTED);
        $friendsRepo->add($relativeFriend, $bridge, User::FRIEND_ACCEPTED);
        $friendsRepo->add($bridge, $viewer, User::FRIEND_ACCEPTED);

        $friendSub = Subscription::factory()->create([
            'user_id' => $friend->id,
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'state' => true,
            'is_passenger' => false,
            'trip_date' => null,
        ]);
        $fofSub = Subscription::factory()->create([
            'user_id' => $relativeFriend->id,
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
            'friendship_type_id' => Trip::PRIVACY_FOF,
            'path' => $path,
            'trip_date' => Carbon::now()->addDays(2),
            'is_passenger' => false,
        ]);

        $rows = $this->repo()->search($viewer, $trip);

        $this->assertCount(2, $rows);
        $ids = $rows->pluck('id')->all();
        $this->assertContains($friendSub->id, $ids);
        $this->assertContains($fofSub->id, $ids);
    }

    public function test_search_without_path_uses_distance_filters_for_start_and_end_points(): void
    {
        // Mutation intent: execute no-path branch and keep both distance checks ("from" and "to").
        // Kills: 1b76ab8186c84b27, bbc0392c2ec13fe3 and protects path-filter mutations via else branch coverage.
        $viewer = User::factory()->create();
        $nearFrom = ['lat' => -34.0000, 'lng' => -58.0000];
        $nearTo = ['lat' => -34.0010, 'lng' => -58.0010];
        $farFrom = ['lat' => -35.0000, 'lng' => -59.0000];
        $farTo = ['lat' => -36.0000, 'lng' => -60.0000];
        $nearFromTrig = $this->trig($nearFrom['lat'], $nearFrom['lng']);
        $nearToTrig = $this->trig($nearTo['lat'], $nearTo['lng']);
        $farFromTrig = $this->trig($farFrom['lat'], $farFrom['lng']);
        $farToTrig = $this->trig($farTo['lat'], $farTo['lng']);

        $match = Subscription::factory()->create([
            'user_id' => User::factory()->create()->id,
            'state' => true,
            'is_passenger' => false,
            'trip_date' => null,
            'from_address' => 'near',
            'to_address' => 'near',
            'from_radio' => 500000,
            'to_radio' => 500000,
            'from_sin_lat' => $nearFromTrig['sin_lat'],
            'from_sin_lng' => $nearFromTrig['sin_lng'],
            'from_cos_lat' => $nearFromTrig['cos_lat'],
            'from_cos_lng' => $nearFromTrig['cos_lng'],
            'to_sin_lat' => $nearToTrig['sin_lat'],
            'to_sin_lng' => $nearToTrig['sin_lng'],
            'to_cos_lat' => $nearToTrig['cos_lat'],
            'to_cos_lng' => $nearToTrig['cos_lng'],
        ]);

        Subscription::factory()->create([
            'user_id' => User::factory()->create()->id,
            'state' => true,
            'is_passenger' => false,
            'trip_date' => null,
            'from_address' => 'far',
            'to_address' => 'near',
            'from_radio' => 1000,
            'to_radio' => 500000,
            'from_sin_lat' => $farFromTrig['sin_lat'],
            'from_sin_lng' => $farFromTrig['sin_lng'],
            'from_cos_lat' => $farFromTrig['cos_lat'],
            'from_cos_lng' => $farFromTrig['cos_lng'],
            'to_sin_lat' => $nearToTrig['sin_lat'],
            'to_sin_lng' => $nearToTrig['sin_lng'],
            'to_cos_lat' => $nearToTrig['cos_lat'],
            'to_cos_lng' => $nearToTrig['cos_lng'],
        ]);

        Subscription::factory()->create([
            'user_id' => User::factory()->create()->id,
            'state' => true,
            'is_passenger' => false,
            'trip_date' => null,
            'from_address' => 'near',
            'to_address' => 'far',
            'from_radio' => 500000,
            'to_radio' => 1000,
            'from_sin_lat' => $nearFromTrig['sin_lat'],
            'from_sin_lng' => $nearFromTrig['sin_lng'],
            'from_cos_lat' => $nearFromTrig['cos_lat'],
            'from_cos_lng' => $nearFromTrig['cos_lng'],
            'to_sin_lat' => $farToTrig['sin_lat'],
            'to_sin_lng' => $farToTrig['sin_lng'],
            'to_cos_lat' => $farToTrig['cos_lat'],
            'to_cos_lng' => $farToTrig['cos_lng'],
        ]);

        $trip = Trip::factory()->create([
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'path' => '',
            'trip_date' => Carbon::now()->addDays(2),
            'is_passenger' => false,
        ]);
        $trip->setRelation('points', collect([
            (object) $nearFromTrig,
            (object) $nearToTrig,
        ]));

        $rows = $this->repo()->search($viewer, $trip);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->is($match));
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

    public function test_get_potential_node_uses_both_lat_and_lng_bounds_with_equality_edges(): void
    {
        // Mutation intent: preserve bbox initialization/comparators and the lng whereBetween clause.
        // Kills: 6cd9a519574e6be6, 1c9b426fe8498e14, 29420b1c5f9927bd, 6779b2d5561945a0,
        //        ad2cfd333cf4b208, bd146720a5417793, 78ce6027d2a4770a, d4fa360dd48008e5,
        //        8c5efeb3827d98d5, 486b69dbdf41772e, 9c8bff4b8cc4c12b.
        $n1 = $this->makeNode(['lat' => -30.0, 'lng' => -55.0]);
        $n2 = $this->makeNode(['lat' => -30.0, 'lng' => -56.0]);
        $edge = $this->makeNode(['lat' => -30.0, 'lng' => -55.5, 'name' => 'EdgeLatEqual']);
        $outsideLng = $this->makeNode(['lat' => -30.0, 'lng' => -57.0, 'name' => 'OutsideLng']);

        $found = $this->repo()->getPotentialNode($n1, $n2);

        $this->assertNotNull($found);
        $this->assertContains($found->id, [$n1->id, $n2->id, $edge->id]);
        $this->assertNotSame($outsideLng->id, $found->id);
    }
}
