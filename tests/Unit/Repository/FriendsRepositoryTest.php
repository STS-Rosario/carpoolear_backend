<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Models\Trip;
use STS\Models\TripVisibility;
use STS\Models\User;
use STS\Repository\FriendsRepository;
use Tests\TestCase;

class FriendsRepositoryTest extends TestCase
{
    private function repo(): FriendsRepository
    {
        return new FriendsRepository;
    }

    public function test_add_pending_does_not_insert_trip_visibility(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $u2->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $this->repo()->add($u1, $u2, User::FRIEND_REQUEST);

        $this->assertTrue($u1->allFriends()->where('users.id', $u2->id)->exists());
        $this->assertSame(0, TripVisibility::query()->where('user_id', $u1->id)->where('trip_id', $trip->id)->count());
    }

    public function test_add_accepted_inserts_visibility_for_viewer_on_friends_only_trips(): void
    {
        $viewer = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $this->repo()->add($viewer, $driver, User::FRIEND_ACCEPTED);

        $this->assertDatabaseHas('user_visibility_trip', [
            'user_id' => $viewer->id,
            'trip_id' => $trip->id,
        ]);
    }

    public function test_add_accepted_inserts_visibility_for_friend_of_friends_trips(): void
    {
        $viewer = User::factory()->create();
        $middle = User::factory()->create();
        $driver = User::factory()->create();

        $this->repo()->add($middle, $driver, User::FRIEND_ACCEPTED);

        $fofTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_FOF,
        ]);

        $this->repo()->add($viewer, $middle, User::FRIEND_ACCEPTED);

        $this->assertDatabaseHas('user_visibility_trip', [
            'user_id' => $viewer->id,
            'trip_id' => $fofTrip->id,
        ]);
    }

    public function test_delete_removes_friend_edge_and_trip_visibility_rows(): void
    {
        $viewer = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $this->repo()->add($viewer, $driver, User::FRIEND_ACCEPTED);
        $this->assertDatabaseHas('user_visibility_trip', [
            'user_id' => $viewer->id,
            'trip_id' => $trip->id,
        ]);

        $this->repo()->delete($viewer, $driver);

        $this->assertFalse($viewer->fresh()->allFriends()->where('users.id', $driver->id)->exists());
        $this->assertSame(0, TripVisibility::query()->where('user_id', $viewer->id)->where('trip_id', $trip->id)->count());
    }

    public function test_get_filters_by_friend_user_state_and_search_value(): void
    {
        $u1 = User::factory()->create();
        $f1 = User::factory()->create(['name' => 'ZetaFriendAlpha']);
        $f2 = User::factory()->create(['name' => 'OtherBuddy']);
        $this->repo()->add($u1, $f1, User::FRIEND_ACCEPTED);
        $this->repo()->add($u1, $f2, User::FRIEND_ACCEPTED);

        $byId = $this->repo()->get($u1, $f1, User::FRIEND_ACCEPTED, []);
        $this->assertCount(1, $byId);
        $this->assertSame($f1->id, $byId->first()->id);

        $search = $this->repo()->get($u1, null, User::FRIEND_ACCEPTED, ['value' => 'Zeta']);
        $this->assertCount(1, $search);
        $this->assertSame($f1->id, $search->first()->id);
    }

    public function test_get_paginates_when_page_size_provided(): void
    {
        $u1 = User::factory()->create();
        $f1 = User::factory()->create(['name' => 'PagA']);
        $f2 = User::factory()->create(['name' => 'PagB']);
        $this->repo()->add($u1, $f1, User::FRIEND_ACCEPTED);
        $this->repo()->add($u1, $f2, User::FRIEND_ACCEPTED);

        $page = $this->repo()->get($u1, null, User::FRIEND_ACCEPTED, ['page' => 1, 'page_size' => 1]);
        $this->assertCount(1, $page->items());
    }

    public function test_get_pending_returns_users_who_sent_request_to_target(): void
    {
        $requester = User::factory()->create();
        $target = User::factory()->create();

        $this->repo()->add($requester, $target, User::FRIEND_REQUEST);

        $pending = $this->repo()->getPending($target);

        $this->assertCount(1, $pending);
        $this->assertSame($requester->id, $pending->first()->id);
    }

    public function test_closest_friend_detects_shared_accepted_friend(): void
    {
        $u1 = User::factory()->create();
        $middle = User::factory()->create();
        $u2 = User::factory()->create();

        $this->repo()->add($u1, $middle, User::FRIEND_ACCEPTED);
        $this->repo()->add($middle, $u2, User::FRIEND_ACCEPTED);

        $this->assertTrue($this->repo()->closestFriend($u1, $u2));
        $this->assertFalse($this->repo()->closestFriend($u1, User::factory()->create()));
    }

    public function test_undo_friend_trip_visibility_matches_delete_sql_shape(): void
    {
        $viewer = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        DB::table('user_visibility_trip')->insert([
            'user_id' => $viewer->id,
            'trip_id' => $trip->id,
        ]);

        $this->repo()->delete($viewer, $driver);

        $this->assertSame(0, DB::table('user_visibility_trip')->where('user_id', $viewer->id)->where('trip_id', $trip->id)->count());
    }
}
