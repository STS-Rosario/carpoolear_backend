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

    public function test_add_accepts_numeric_string_friend_state_from_request_payloads(): void
    {
        // Mutation intent: keep loose accepted-state comparison for payload coercion ("1" vs 1).
        // Kills: 56370d7f8538bd76 (Line 17 EqualToIdentical).
        $viewer = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $this->repo()->add($viewer, $driver, (string) User::FRIEND_ACCEPTED);

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

    public function test_get_search_matches_substring_in_middle_of_name(): void
    {
        // Mutation intent: keep full "%value%" wildcard semantics in name search.
        // Kills: a98a28c1baf267eb, 008590db7b9f84cf (Line 41 concat mutations).
        $u1 = User::factory()->create();
        $middleMatch = User::factory()->create(['name' => 'AlphaZetaOmega']);
        $suffixOnly = User::factory()->create(['name' => 'OmegaZeta']);
        $prefixOnly = User::factory()->create(['name' => 'ZetaOmega']);

        $this->repo()->add($u1, $middleMatch, User::FRIEND_ACCEPTED);
        $this->repo()->add($u1, $suffixOnly, User::FRIEND_ACCEPTED);
        $this->repo()->add($u1, $prefixOnly, User::FRIEND_ACCEPTED);

        $search = $this->repo()->get($u1, null, User::FRIEND_ACCEPTED, ['value' => 'ZetaO']);

        $this->assertCount(2, $search);
        $this->assertTrue($search->pluck('id')->contains($middleMatch->id));
        $this->assertTrue($search->pluck('id')->contains($prefixOnly->id));
        $this->assertFalse($search->pluck('id')->contains($suffixOnly->id));
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

    public function test_get_pending_excludes_requests_sent_to_other_users(): void
    {
        // Mutation intent: enforce pending-query relation filters for the target user only.
        // Kills: 1592288398f6cf6e (Line 53 RemoveMethodCall).
        $requesterForTarget = User::factory()->create();
        $requesterForOther = User::factory()->create();
        $target = User::factory()->create();
        $otherTarget = User::factory()->create();

        $this->repo()->add($requesterForTarget, $target, User::FRIEND_REQUEST);
        $this->repo()->add($requesterForOther, $otherTarget, User::FRIEND_REQUEST);

        $pending = $this->repo()->getPending($target);

        $this->assertCount(1, $pending);
        $this->assertSame($requesterForTarget->id, $pending->first()->id);
    }

    public function test_get_pending_excludes_accepted_friends_who_share_target_edge(): void
    {
        // Mutation intent: preserve `$q->where('state', UserModel::FRIEND_REQUEST)` inside getPending (RemoveMethodCall on state filter).
        $target = User::factory()->create();
        $requester = User::factory()->create();
        $alreadyAccepted = User::factory()->create();

        $this->repo()->add($alreadyAccepted, $target, User::FRIEND_ACCEPTED);
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

    public function test_delete_removes_visibility_for_friend_of_friend_trips(): void
    {
        // Mutation intent: ensure FoF cleanup iterates and deletes related visibility rows.
        // Kills: 426d2e54daf776f2 (Line 115 RemoveMethodCall), 5fc0007a1c0c885e (Line 118 RemoveMethodCall).
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

        $this->repo()->delete($viewer, $middle);

        $this->assertDatabaseMissing('user_visibility_trip', [
            'user_id' => $viewer->id,
            'trip_id' => $fofTrip->id,
        ]);
    }
}
