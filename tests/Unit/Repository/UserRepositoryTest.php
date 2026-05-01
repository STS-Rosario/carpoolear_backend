<?php

namespace Tests\Unit\Repository;

use Illuminate\Support\Facades\DB;
use Mockery;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\FriendsRepository;
use STS\Repository\UserRepository;
use STS\Services\Notifications\Models\DatabaseNotification;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    private function repo(): UserRepository
    {
        return new UserRepository;
    }

    public function test_create_persists_user(): void
    {
        $attrs = User::factory()->make()->toArray();
        $attrs['email'] = 'create-'.uniqid('', true).'@example.com';

        $user = $this->repo()->create($attrs);

        $this->assertNotNull($user->id);
        $this->assertSame($attrs['email'], $user->email);
    }

    public function test_update_strips_is_admin_from_payload(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => false])->saveQuietly();

        $this->repo()->update($user, [
            'name' => 'Updated Name',
            'is_admin' => true,
        ]);

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertFalse((bool) $user->is_admin);
    }

    public function test_show_nulls_private_note_and_loads_relations(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['private_note' => 'secret admin note'])->saveQuietly();

        $shown = $this->repo()->show($user->id);

        $this->assertNotNull($shown);
        $this->assertNull($shown->private_note);
        // Mutation intent: preserve eager-load array in show().
        $this->assertTrue($shown->relationLoaded('accounts'));
        $this->assertTrue($shown->relationLoaded('donations'));
        $this->assertTrue($shown->relationLoaded('referencesReceived'));
        $this->assertTrue($shown->relationLoaded('cars'));
    }

    public function test_show_returns_null_when_user_not_found(): void
    {
        // Mutation intent: keep `$user` null guard before `private_note` reset (~lines 43–45).
        $missingId = (User::query()->max('id') ?? 0) + 999999;

        $this->assertNull($this->repo()->show($missingId));
    }

    public function test_accept_terms_and_update_photo(): void
    {
        $user = User::factory()->create(['terms_and_conditions' => false]);

        $accepted = $this->repo()->acceptTerms($user);
        $this->assertNotNull($accepted);
        $this->assertSame($user->id, $accepted->id);
        $this->assertTrue($user->fresh()->terms_and_conditions);

        $updated = $this->repo()->updatePhoto($user, 'avatar-99.png');
        $this->assertNotNull($updated);
        $this->assertSame($user->id, $updated->id);
        $this->assertSame('avatar-99.png', $user->fresh()->image);
    }

    public function test_accept_terms_invokes_save(): void
    {
        // Mutation intent: preserve `$user->save()` in acceptTerms (~54–55 RemoveMethodCall).
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('save')->once()->andReturn(true);

        $this->repo()->acceptTerms($user);
    }

    public function test_update_photo_invokes_save(): void
    {
        // Mutation intent: preserve `$user->save()` in updatePhoto (~62–63 RemoveMethodCall).
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('save')->once()->andReturn(true);

        $out = $this->repo()->updatePhoto($user, 'mock-avatar.png');

        $this->assertSame($user, $out);
    }

    public function test_get_user_by(): void
    {
        $user = User::factory()->create(['email' => 'byemail-'.uniqid('', true).'@example.com']);

        $found = $this->repo()->getUserBy('email', $user->email);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($user));
    }

    public function test_get_user_by_returns_null_when_no_match(): void
    {
        // Mutation intent: preserve `User::where($key, $value)->first()` absent-row behavior (~68–71).
        $this->assertNull($this->repo()->getUserBy('email', 'missing-getuserby-'.uniqid('', true).'@example.com'));
    }

    public function test_search_users_null_returns_null_and_limits_matches(): void
    {
        $this->assertNull($this->repo()->searchUsers(null));
        $this->assertNull($this->repo()->searchUsers(''));

        $needle = 'SearchNeedleXy'.substr(uniqid('', true), 0, 8);
        User::factory()->count(10)->create(['name' => $needle.' Person']);

        $rows = $this->repo()->searchUsers($needle);
        $this->assertCount(9, $rows);
    }

    public function test_search_users_matches_name_email_doc_or_phone(): void
    {
        $uByName = User::factory()->create(['name' => 'AlphaNeedleName']);
        $uByEmail = User::factory()->create(['email' => 'needle-email-777@example.com']);
        $uByDoc = User::factory()->create(['nro_doc' => 'DOC-NEEDLE-777']);
        $uByPhone = User::factory()->create(['mobile_phone' => '+54911-NEEDLE-777']);

        // Mutation intent: preserve each OR where in searchUsers(name/email/nro_doc/mobile_phone).
        $byName = $this->repo()->searchUsers('NeedleName');
        $this->assertCount(1, $byName);
        $this->assertSame($uByName->id, $byName->first()->id);

        $byEmail = $this->repo()->searchUsers('needle-email-777');
        $this->assertCount(1, $byEmail);
        $this->assertSame($uByEmail->id, $byEmail->first()->id);

        $byDoc = $this->repo()->searchUsers('DOC-NEEDLE-777');
        $this->assertCount(1, $byDoc);
        $this->assertSame($uByDoc->id, $byDoc->first()->id);

        $byPhone = $this->repo()->searchUsers('NEEDLE-777');
        $this->assertTrue($byPhone->pluck('id')->contains($uByPhone->id));
    }

    public function test_search_users_returns_empty_collection_when_no_matches(): void
    {
        // Mutation intent: preserve OR query chain when every predicate misses (~76–87).
        User::factory()->create(['name' => 'AliceKeep']);

        $rows = $this->repo()->searchUsers('NoHitTokenZZ'.uniqid('', true));

        $this->assertCount(0, $rows);
    }

    public function test_search_users_orders_by_name_and_eager_loads_accounts_and_cars(): void
    {
        // Mutation intent: preserve with(['accounts', 'cars']) and orderBy('name').
        $a = User::factory()->create(['name' => 'A SearchOrder']);
        $z = User::factory()->create(['name' => 'Z SearchOrder']);

        $rows = $this->repo()->searchUsers('SearchOrder');

        $this->assertGreaterThanOrEqual(2, $rows->count());
        $this->assertSame($a->id, $rows->first()->id);
        $this->assertTrue($rows->first()->relationLoaded('accounts'));
        $this->assertTrue($rows->first()->relationLoaded('cars'));
        $this->assertTrue($rows->last()->relationLoaded('accounts'));
        $this->assertTrue($rows->last()->relationLoaded('cars'));
        $this->assertSame($z->id, $rows->last()->id);
    }

    public function test_index_excludes_self_banned_inactive_and_accepted_friends(): void
    {
        $self = User::factory()->create(['name' => 'Self User', 'active' => true, 'banned' => false]);
        $friend = User::factory()->create(['name' => 'Friend User', 'active' => true, 'banned' => false]);
        $stranger = User::factory()->create(['name' => 'Stranger User', 'active' => true, 'banned' => false]);
        $inactive = User::factory()->create(['name' => 'Inactive User', 'active' => false, 'banned' => false]);

        $this->repo()->addFriend($self, $friend, 'test');

        $rows = $this->repo()->index($self, null);
        $ids = $rows->pluck('id');

        $this->assertFalse($ids->contains($self->id));
        $this->assertFalse($ids->contains($friend->id));
        $this->assertTrue($ids->contains($stranger->id));
        $this->assertFalse($ids->contains($inactive->id));
    }

    public function test_index_returns_empty_when_only_self_exists(): void
    {
        // Mutation intent: `where('id', '<>', $user->id)` leaves zero rows (~94–96).
        $self = User::factory()->create(['active' => true, 'banned' => false]);

        $rows = $this->repo()->index($self->fresh(), null);

        $this->assertCount(0, $rows);
    }

    public function test_index_filters_by_search_text_and_sets_state_none(): void
    {
        $self = User::factory()->create(['name' => 'IndexSelf']);
        User::factory()->create(['name' => 'OtherPersonX', 'active' => true, 'banned' => false]);
        $match = User::factory()->create(['name' => 'FindMeZebraX', 'active' => true, 'banned' => false]);

        $rows = $this->repo()->index($self, 'ZebraX');
        $this->assertCount(1, $rows);
        $this->assertSame($match->id, $rows->first()->id);
        $this->assertSame('none', $rows->first()->state);
    }

    public function test_index_sets_state_request_when_pending_friend_edge_exists(): void
    {
        // Mutation intent: preserve map branch `$u->pivot->state == User::FRIEND_REQUEST` → `$item->state = 'request'`.
        $suffix = substr(uniqid('', true), 0, 8);
        $self = User::factory()->create([
            'name' => 'IdxReqSelf'.$suffix,
            'active' => true,
            'banned' => false,
        ]);
        $pendingPeer = User::factory()->create([
            'name' => 'IdxReqPending'.$suffix,
            'active' => true,
            'banned' => false,
        ]);

        // `allFriends()` is uid1→uid2; index reads `$user->allFriends()` so the REQUEST row must have uid1=$self.
        (new FriendsRepository)->add($self, $pendingPeer, User::FRIEND_REQUEST);

        $rows = $this->repo()->index($self->fresh(), 'IdxReqPending'.$suffix);

        $this->assertCount(1, $rows);
        $this->assertSame($pendingPeer->id, $rows->first()->id);
        $this->assertSame('request', $rows->first()->state);
    }

    public function test_index_sets_state_friend_when_accepted_edge_uid1_only(): void
    {
        // Mutation intent: preserve non-REQUEST pivot branch → `$item->state = 'friend'` (lines ~118–119).
        // Bidirectional `addFriend` excludes peers via `whereDoesntHave('friends')`; a single uid1→uid2 ACCEPTED
        // row lets `$user->allFriends()` match while the peer may still lack reverse `friends()` toward `$user`.
        $suffix = substr(uniqid('', true), 0, 8);
        $self = User::factory()->create([
            'name' => 'IdxFrSelf'.$suffix,
            'active' => true,
            'banned' => false,
        ]);
        $peer = User::factory()->create([
            'name' => 'IdxFrPeer'.$suffix,
            'active' => true,
            'banned' => false,
        ]);

        (new FriendsRepository)->add($self, $peer, User::FRIEND_ACCEPTED);

        $rows = $this->repo()->index($self->fresh(), 'IdxFrPeer'.$suffix);

        $this->assertCount(1, $rows);
        $this->assertSame($peer->id, $rows->first()->id);
        $this->assertSame('friend', $rows->first()->state);
    }

    public function test_add_friend_and_delete_friend_sync_bidirectional_pivot(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $repo = $this->repo();

        $provider = 'unit'.substr(uniqid('', true), 0, 8);
        $repo->addFriend($a, $b, $provider);

        $this->assertTrue($a->friends()->where('users.id', $b->id)->exists());
        $this->assertTrue($b->friends()->where('users.id', $a->id)->exists());
        $this->assertDatabaseHas('friends', ['uid1' => $a->id, 'uid2' => $b->id, 'origin' => $provider]);
        $this->assertDatabaseHas('friends', ['uid1' => $b->id, 'uid2' => $a->id, 'origin' => $provider]);

        $repo->deleteFriend($a, $b);

        $this->assertFalse($a->fresh()->friends()->where('users.id', $b->id)->exists());
        $this->assertFalse($b->fresh()->friends()->where('users.id', $a->id)->exists());
    }

    public function test_password_reset_token_round_trip(): void
    {
        $user = User::factory()->create(['email' => 'reset-'.uniqid('', true).'@example.com']);
        $token = 'tok-'.uniqid('', true);

        $this->repo()->storeResetToken($user, $token);

        $this->assertDatabaseHas('password_resets', ['email' => $user->email, 'token' => $token]);

        $resolved = $this->repo()->getUserByResetToken($token);
        $this->assertTrue($resolved->is($user));

        $last = $this->repo()->getLastPasswordReset($user->email);
        $this->assertSame($token, $last->token);

        $this->repo()->deleteResetToken('token', $token);
        $this->assertDatabaseMissing('password_resets', ['token' => $token]);
    }

    public function test_password_reset_token_lookups_return_null_when_missing(): void
    {
        // Mutation intent: preserve early-exit when no `password_resets` row (`getUserByResetToken` ~164–167)
        // and empty `getLastPasswordReset` (~172–175).
        $this->assertNull($this->repo()->getUserByResetToken('missing-token-'.uniqid('', true)));
        $this->assertNull($this->repo()->getLastPasswordReset('missing-email-'.uniqid('', true).'@example.com'));
    }

    public function test_delete_reset_token_leaves_table_unchanged_when_no_rows_match(): void
    {
        // Mutation intent: preserve `where($key, $value)->delete()` zero-match path (~157–159).
        $before = DB::table('password_resets')->count();

        $this->repo()->deleteResetToken('token', 'noSuchTok'.uniqid('', true));

        $this->assertSame($before, DB::table('password_resets')->count());
    }

    public function test_get_user_by_reset_token_returns_null_when_email_missing_from_users(): void
    {
        // Mutation intent: `User::where('email', $pr->email)->first()` miss (~164–167).
        $token = 'orph-email-tok-'.uniqid('', true);
        DB::table('password_resets')->insert([
            'email' => 'ghost-'.uniqid('', true).'@example.com',
            'token' => $token,
            'created_at' => now(),
        ]);

        $this->assertNull($this->repo()->getUserByResetToken($token));
    }

    public function test_get_notifications_respects_unread_flag(): void
    {
        $user = User::factory()->create();
        DatabaseNotification::create([
            'user_id' => $user->id,
            'type' => 'Tests\\DummyNotification',
            'read_at' => null,
        ]);
        DatabaseNotification::create([
            'user_id' => $user->id,
            'type' => 'Tests\\DummyNotification2',
            'read_at' => now(),
        ]);

        $all = $this->repo()->getNotifications($user->fresh(), false);
        $this->assertCount(2, $all);

        $unread = $this->repo()->getNotifications($user->fresh(), true);
        $this->assertCount(1, $unread);
        $this->assertNull($unread->first()->read_at);
    }

    public function test_get_notifications_returns_empty_when_user_has_none(): void
    {
        // Mutation intent: delegate paths `$user->notifications` / `$user->unreadNotifications` (~178–184).
        $user = User::factory()->create();

        $this->assertCount(0, $this->repo()->getNotifications($user->fresh(), false));
        $this->assertCount(0, $this->repo()->getNotifications($user->fresh(), true));
    }

    public function test_mark_notification_sets_read_at(): void
    {
        $user = User::factory()->create();
        $n = DatabaseNotification::create([
            'user_id' => $user->id,
            'type' => 'Tests\\MarkReadNotification',
            'read_at' => null,
        ]);

        $this->repo()->markNotification($n);

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_notification_invokes_readed(): void
    {
        // Mutation intent: preserve `$notification->readed()` delegate (~187–190 RemoveMethodCall).
        $n = Mockery::mock(DatabaseNotification::class);
        $n->shouldReceive('readed')->once();

        $this->repo()->markNotification($n);
    }

    public function test_unanswered_conversation_or_requests_by_trip(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        $conversation = Conversation::factory()->create(['trip_id' => $trip->id]);
        Message::create([
            'user_id' => $passenger->id,
            'conversation_id' => $conversation->id,
            'text' => 'Hi',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $before = $this->repo()->unansweredConversationOrRequestsByTrip($driver->id, $trip->id);
        $this->assertSame(2, $before);

        Message::create([
            'user_id' => $driver->id,
            'conversation_id' => $conversation->id,
            'text' => 'Reply',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $after = $this->repo()->unansweredConversationOrRequestsByTrip($driver->id, $trip->id);
        $this->assertSame(1, $after);
    }

    public function test_unanswered_conversation_or_requests_by_trip_returns_zero_when_none(): void
    {
        // Mutation intent: both `count()` branches (~195–208) zero — no pending passenger rows, no qualifying conversations.
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->assertSame(0, $this->repo()->unansweredConversationOrRequestsByTrip($driver->id, $trip->id));
    }
}
