<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\Car;
use STS\Models\Conversation;
use STS\Models\Device;
use STS\Models\Donation;
use STS\Models\ManualIdentityValidation;
use STS\Models\Passenger;
use STS\Models\Payment;
use STS\Models\PaymentAttempt;
use STS\Models\Rating;
use STS\Models\References;
use STS\Models\SocialAccount;
use STS\Models\Subscription;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketReply;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_get_jwt_identifier_and_custom_claims(): void
    {
        $user = User::factory()->create();

        $this->assertSame($user->id, $user->getJWTIdentifier());
        $this->assertSame([], $user->getJWTCustomClaims());
    }

    public function test_friendship_constants(): void
    {
        $this->assertSame(0, User::FRIEND_REQUEST);
        $this->assertSame(1, User::FRIEND_ACCEPTED);
        $this->assertSame(2, User::FRIEND_REJECT);
        $this->assertSame(0, User::FRIENDSHIP_SYSTEM);
        $this->assertSame(1, User::FRIENDSHIP_FACEBOOK);
    }

    public function test_friendship_constants_remain_distinct_where_semantics_require_it(): void
    {
        // Mutation intent: `IncrementInteger` / `DecrementInteger` on friend constants (~21–29) must not merge ACCEPTED/REJECT or FACEBOOK/SYSTEM.
        $this->assertNotSame(User::FRIEND_ACCEPTED, User::FRIEND_REJECT);
        $this->assertNotSame(User::FRIENDSHIP_FACEBOOK, User::FRIENDSHIP_SYSTEM);
        $this->assertLessThan(User::FRIEND_REJECT, User::FRIEND_ACCEPTED);
    }

    public function test_fillable_lists_mass_assignment_columns(): void
    {
        $expected = [
            'name',
            'username',
            'email',
            'password',
            'terms_and_conditions',
            'birthday',
            'gender',
            'banned',
            'nro_doc',
            'description',
            'private_note',
            'mobile_phone',
            'phone_verified',
            'phone_verified_at',
            'image',
            'active',
            'activation_token',
            'emails_notifications',
            'last_connection',
            'has_pin',
            'is_member',
            'monthly_donate',
            'unaswered_messages_limit',
            'do_not_alert_request_seat',
            'do_not_alert_accept_passenger',
            'do_not_alert_pending_rates',
            'do_not_alert_pricing',
            'autoaccept_requests',
            'on_boarding_view',
            'driver_is_verified',
            'driver_data_docs',
            'account_number',
            'account_type',
            'account_bank',
            'data_visibility',
            'identity_validated',
            'identity_validated_at',
            'identity_validation_type',
            'identity_validation_rejected_at',
            'identity_validation_reject_reason',
            'validate_by_date',
        ];

        $this->assertSame($expected, (new User)->getFillable());
    }

    public function test_hidden_lists_serialization_suppressed_attributes(): void
    {
        $this->assertSame(
            ['password', 'remember_token', 'terms_and_conditions', 'private_note'],
            (new User)->getHidden()
        );
    }

    public function test_casts_include_boolean_datetime_and_array_columns(): void
    {
        $expected = [
            'banned' => 'boolean',
            'terms_and_conditions' => 'boolean',
            'active' => 'boolean',
            'is_admin' => 'boolean',
            'has_pin' => 'boolean',
            'is_member' => 'boolean',
            'monthly_donate' => 'boolean',
            'do_not_alert_request_seat' => 'boolean',
            'do_not_alert_accept_passenger' => 'boolean',
            'do_not_alert_pending_rates' => 'boolean',
            'driver_is_verified' => 'boolean',
            'emails_notifications' => 'boolean',
            'driver_data_docs' => 'array',
            'last_connection' => 'datetime',
            'identity_validated' => 'boolean',
            'identity_validated_at' => 'datetime',
            'identity_validation_rejected_at' => 'datetime',
            'validate_by_date' => 'date',
            'phone_verified' => 'boolean',
            'phone_verified_at' => 'datetime',
        ];

        $casts = (new User)->getCasts();
        foreach ($expected as $attribute => $type) {
            $this->assertSame($type, $casts[$attribute] ?? null, 'casts['.$attribute.']');
        }
    }

    public function test_appends_list_rating_and_reference_accessors(): void
    {
        $expected = ['positive_ratings', 'negative_ratings', 'references'];
        $this->assertSame($expected, (new User)->getAppends());
    }

    public function test_to_array_includes_each_appended_accessor_key(): void
    {
        $user = User::factory()->create();
        $array = $user->fresh()->toArray();
        foreach ((new User)->getAppends() as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }

    public function test_accounts_cars_and_devices_relationships(): void
    {
        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => 'ext-'.uniqid('', true),
            'provider' => 'facebook',
        ]);
        Car::factory()->create(['user_id' => $user->id]);

        $device = new Device;
        $device->forceFill([
            'user_id' => $user->id,
            'device_id' => 'd-'.uniqid('', true),
            'device_type' => 'ios',
            'session_id' => 's-'.uniqid('', true),
            'app_version' => 1,
            'notifications' => true,
            'language' => 'es',
            'last_activity' => now()->toDateString(),
        ])->save();

        $user = $user->fresh();
        $this->assertSame(1, $user->accounts()->count());
        $this->assertSame(1, $user->cars()->count());
        $this->assertSame(1, $user->devices()->count());
    }

    public function test_donation_records_has_many_without_month_filter(): void
    {
        $user = User::factory()->create();

        Donation::query()->create([
            'user_id' => $user->id,
            'month' => '2025-01-15 00:00:00',
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 100,
        ]);
        Donation::query()->create([
            'user_id' => $user->id,
            'month' => '2026-03-01 00:00:00',
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 200,
        ]);

        $this->assertSame(2, $user->fresh()->donationRecords()->count());
    }

    public function test_age_returns_year_component_when_birthday_set(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $user = User::factory()->create(['birthday' => '2000-06-10']);

        $age = $user->fresh()->age();
        $this->assertIsInt($age);
        $this->assertGreaterThanOrEqual(25, $age);
        $this->assertLessThanOrEqual(26, $age);
    }

    public function test_driver_data_docs_casts_to_array(): void
    {
        $payload = ['license' => 'AB123', 'expires' => '2030-01-01'];
        $user = User::factory()->create(['driver_data_docs' => $payload]);

        $user = $user->fresh();
        $this->assertEquals($payload, $user->driver_data_docs);
        $this->assertIsArray($user->driver_data_docs);
    }

    public function test_to_array_hides_password_remember_token_and_private_note(): void
    {
        $user = User::factory()->create([
            'private_note' => 'admin only',
        ]);
        $array = $user->fresh()->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayNotHasKey('terms_and_conditions', $array);
        $this->assertArrayNotHasKey('private_note', $array);
        $this->assertArrayHasKey('email', $array);
    }

    public function test_all_friends_applies_pivot_state_filter_when_state_is_truthy(): void
    {
        // Mutation intent: `RemoveMethodCall` on `wherePivot('state', $state)` (~161) when `$state` is truthy.
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $origin = 'u'.substr(uniqid('', true), 0, 10);
        $ts = now()->toDateTimeString();

        DB::table('friends')->insert([
            ['uid1' => $alice->id, 'uid2' => $bob->id, 'origin' => $origin, 'state' => User::FRIEND_REQUEST, 'created_at' => $ts, 'updated_at' => $ts],
            ['uid1' => $alice->id, 'uid2' => $carol->id, 'origin' => $origin, 'state' => User::FRIEND_ACCEPTED, 'created_at' => $ts, 'updated_at' => $ts],
        ]);

        $this->assertDatabaseHas('friends', [
            'uid1' => $alice->id,
            'uid2' => $carol->id,
            'state' => 1,
        ]);

        $accepted = $alice->fresh()->allFriends(User::FRIEND_ACCEPTED)->pluck('users.id')->all();
        $this->assertSame([$carol->id], $accepted);
    }

    public function test_relative_friends_includes_two_hop_neighbors(): void
    {
        // Mutation intent: `RemoveMethodCall` on `whereId($u->id)` inside `relativeFriends()` (~179).
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $origin = 'r'.substr(uniqid('', true), 0, 10);
        $ts = now()->toDateTimeString();
        $ac = User::FRIEND_ACCEPTED;

        DB::table('friends')->insert([
            ['uid1' => $alice->id, 'uid2' => $bob->id, 'origin' => $origin, 'state' => $ac, 'created_at' => $ts, 'updated_at' => $ts],
            ['uid1' => $bob->id, 'uid2' => $alice->id, 'origin' => $origin, 'state' => $ac, 'created_at' => $ts, 'updated_at' => $ts],
            ['uid1' => $bob->id, 'uid2' => $carol->id, 'origin' => $origin, 'state' => $ac, 'created_at' => $ts, 'updated_at' => $ts],
            ['uid1' => $carol->id, 'uid2' => $bob->id, 'origin' => $origin, 'state' => $ac, 'created_at' => $ts, 'updated_at' => $ts],
        ]);

        $ids = $carol->fresh()->relativeFriends()->pluck('id')->all();
        $this->assertContains($alice->id, $ids);
    }

    public function test_support_ticket_relations_return_has_many(): void
    {
        $this->assertInstanceOf(HasMany::class, (new User)->supportTickets());
        $this->assertInstanceOf(HasMany::class, (new User)->supportTicketReplies());
    }

    public function test_support_ticket_and_reply_counts_track_rows(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'type' => 'billing',
            'subject' => 'Need help',
            'status' => 'Open',
            'priority' => 'normal',
        ]);
        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => false,
            'message_markdown' => 'Hello',
        ]);

        $user = $user->fresh();
        $this->assertSame(1, $user->supportTickets()->count());
        $this->assertSame(1, $user->supportTicketReplies()->count());
    }

    public function test_donations_relation_filters_to_current_month_only(): void
    {
        // Mutation intent: `RemoveMethodCall` on either month bound in `donations()` (~201–202).
        Carbon::setTestNow('2026-06-15 12:00:00');
        $user = User::factory()->create();

        Donation::query()->create([
            'user_id' => $user->id,
            'month' => '2026-06-10 10:00:00',
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 50,
        ]);
        Donation::query()->create([
            'user_id' => $user->id,
            'month' => '2026-05-10 10:00:00',
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 99,
        ]);

        $this->assertSame(1, $user->fresh()->donations()->count());
        $this->assertSame(2, $user->fresh()->donationRecords()->count());
    }

    public function test_notifications_relation_excludes_deleted_rows(): void
    {
        $user = User::factory()->create();
        $ts = now()->toDateTimeString();
        DB::table('notifications')->insert([
            [
                'user_id' => $user->id,
                'type' => 'Tests\\Notification',
                'read_at' => null,
                'deleted_at' => null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ],
            [
                'user_id' => $user->id,
                'type' => 'Tests\\NotificationDeleted',
                'read_at' => null,
                'deleted_at' => $ts,
                'created_at' => $ts,
                'updated_at' => $ts,
            ],
        ]);

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_trips_filters_by_finalizado_and_activo_states(): void
    {
        // Mutation intent: `ElseIfNegated` / `RemoveMethodCall` on `trips($state)` date branches (~232–235).
        Carbon::setTestNow('2026-06-15 14:00:00');
        $user = User::factory()->create();

        $past = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => '2026-06-10 10:00:00',
        ]);
        $future = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => '2026-06-20 10:00:00',
        ]);

        $user = $user->fresh();
        $this->assertTrue($user->trips(Trip::FINALIZADO)->whereKey($past->id)->exists());
        $this->assertFalse($user->trips(Trip::FINALIZADO)->whereKey($future->id)->exists());
        $this->assertTrue($user->trips(Trip::ACTIVO)->whereKey($future->id)->exists());
        $this->assertFalse($user->trips(Trip::ACTIVO)->whereKey($past->id)->exists());
        $this->assertTrue($user->trips(null)->whereKey($past->id)->exists());
        $this->assertTrue($user->trips(null)->whereKey($future->id)->exists());
    }

    public function test_trips_as_passenger_applies_state_and_hours_filters(): void
    {
        // Mutation intent: `tripsAsPassenger` date branches (~258–261) and hour window (~263–268).
        Carbon::setTestNow('2026-06-15 14:00:00');
        $driver = User::factory()->create();
        $passenger = User::factory()->create();

        $past = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-06-14 10:00:00',
        ]);
        $nearFuture = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-06-15 15:00:00',
        ]);
        $farFuture = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-06-25 15:00:00',
        ]);

        foreach ([$past, $nearFuture, $farFuture] as $trip) {
            Passenger::factory()->create([
                'trip_id' => $trip->id,
                'user_id' => $passenger->id,
                'request_state' => Passenger::STATE_ACCEPTED,
            ]);
        }

        $p = $passenger->fresh();
        $this->assertTrue($p->tripsAsPassenger(Trip::FINALIZADO)->whereKey($past->id)->exists());
        $this->assertFalse($p->tripsAsPassenger(Trip::FINALIZADO)->whereKey($nearFuture->id)->exists());
        $this->assertTrue($p->tripsAsPassenger(Trip::ACTIVO)->whereKey($nearFuture->id)->exists());

        $inWindow = $p->tripsAsPassenger(null, 4)->pluck('id')->all();
        $this->assertContains($nearFuture->id, $inWindow);
        $this->assertNotContains($farFuture->id, $inWindow);
    }

    public function test_trips_requested_and_pending_requests_stack_expected_wheres(): void
    {
        // Mutation intent: `RemoveMethodCall` on nested `where` / `orWhere` blocks (~279–282, ~292–295, ~302–303).
        Carbon::setTestNow('2026-06-15 14:00:00');
        $driver = User::factory()->create();
        $passenger = User::factory()->create();

        $near = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-06-15 15:00:00',
        ]);
        $far = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-06-25 15:00:00',
        ]);

        Passenger::factory()->create([
            'trip_id' => $near->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        Passenger::factory()->create([
            'trip_id' => $far->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $p = $passenger->fresh();
        $requestedNear = $p->tripsRequested(3)->pluck('id')->all();
        $this->assertContains($near->id, $requestedNear);
        $this->assertNotContains($far->id, $requestedNear);

        $this->assertSame(1, $p->pendingRequests(3)->count());
    }

    public function test_rating_given_and_received_require_available_flag(): void
    {
        // Mutation intent: `DecrementInteger` / `IncrementInteger` on `where('available', 1)` (~316, ~324).
        $driver = User::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $ts = now()->toDateTimeString();

        $base = [
            'trip_id' => $trip->id,
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'user_to_type' => 0,
            'user_to_state' => 0,
            'comment' => 'ok',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => 1,
            'voted_hash' => 'vh1',
            'rate_at' => null,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];

        DB::table('rating')->insert(array_merge($base, [
            'rating' => Rating::STATE_POSITIVO,
            'available' => 1,
        ]));
        DB::table('rating')->insert(array_merge($base, [
            'rating' => Rating::STATE_POSITIVO,
            'available' => 0,
        ]));

        $to = $to->fresh();
        $this->assertSame(1, $to->ratingReceived()->count());
        $this->assertSame(1, $from->fresh()->ratingGiven()->count());
    }

    public function test_ratings_accessor_applies_value_filter_and_preserves_typo_in_query_builder(): void
    {
        // Mutation intent: `IfNegated` / `RemoveMethodCall` on optional `where('rating', $value)` (~333–334).
        $driver = User::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $ts = now()->toDateTimeString();
        $base = [
            'trip_id' => $trip->id,
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'user_to_type' => 0,
            'user_to_state' => 0,
            'comment' => 'x',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => 1,
            'voted_hash' => 'vh2',
            'rate_at' => null,
            'available' => 1,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];

        DB::table('rating')->insert(array_merge($base, ['rating' => Rating::STATE_POSITIVO]));
        DB::table('rating')->insert(array_merge($base, ['rating' => Rating::STATE_NEGATIVO]));

        $to = $to->fresh();
        $this->assertSame(2, $to->ratings(null)->count());
        $this->assertSame(1, $to->ratings(Rating::STATE_POSITIVO)->count());
        $this->assertSame(1, $to->ratings(Rating::STATE_NEGATIVO)->count());
    }

    public function test_appended_rating_and_reference_counts_are_integers(): void
    {
        // Mutation intent: `AlwaysReturnNull` on append accessors (~352).
        $driver = User::factory()->create();
        $rater = User::factory()->create();
        $subject = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $ts = now()->toDateTimeString();
        $base = [
            'trip_id' => $trip->id,
            'user_id_from' => $rater->id,
            'user_id_to' => $subject->id,
            'user_to_type' => 0,
            'user_to_state' => 0,
            'comment' => 'x',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => 1,
            'voted_hash' => 'vh3',
            'rate_at' => null,
            'available' => 1,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
        DB::table('rating')->insert(array_merge($base, ['rating' => Rating::STATE_POSITIVO]));
        DB::table('rating')->insert(array_merge($base, ['rating' => Rating::STATE_NEGATIVO]));

        References::query()->create([
            'user_id_from' => $rater->id,
            'user_id_to' => $subject->id,
            'comment' => 'reference text',
        ]);

        $subject = $subject->fresh();
        $this->assertSame(1, $subject->positive_ratings);
        $this->assertSame(1, $subject->negative_ratings);
        $this->assertSame(1, $subject->references);
    }

    public function test_manual_identity_validations_returns_has_many(): void
    {
        $user = User::factory()->create();
        ManualIdentityValidation::query()->create([
            'user_id' => $user->id,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->assertSame(1, $user->fresh()->manualIdentityValidations()->count());
    }

    public function test_passenger_relation_returns_has_many(): void
    {
        $passengerUser = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);

        $u = $passengerUser->fresh();
        $this->assertInstanceOf(HasMany::class, $u->passenger());
        $this->assertSame(1, $u->passenger()->count());
    }

    public function test_subscriptions_relation_returns_has_many(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(HasMany::class, $user->fresh()->subscriptions());
        $this->assertSame(1, $user->fresh()->subscriptions()->count());
    }

    public function test_campaign_donations_relation_returns_has_many(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::query()->create([
            'slug' => 'cd-'.uniqid('', true),
            'title' => 'Campaign',
            'description' => 'D',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => null,
        ]);
        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => null,
            'payment_id' => 'pay-'.uniqid('', true),
            'amount_cents' => 100,
            'name' => null,
            'comment' => null,
            'user_id' => $user->id,
            'status' => 'paid',
        ]);

        $this->assertInstanceOf(HasMany::class, $user->fresh()->campaignDonations());
        $this->assertSame(1, $user->fresh()->campaignDonations()->count());
    }

    public function test_unread_notifications_filters_read_at_null(): void
    {
        $user = User::factory()->create();
        $ts = now()->toDateTimeString();
        DB::table('notifications')->insert([
            [
                'user_id' => $user->id,
                'type' => 'Tests\\UnreadA',
                'read_at' => null,
                'deleted_at' => null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ],
            [
                'user_id' => $user->id,
                'type' => 'Tests\\ReadB',
                'read_at' => $ts,
                'deleted_at' => null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ],
        ]);

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_payments_relation_returns_has_many_and_counts_rows(): void
    {
        $user = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Payment::query()->create([
            'payment_id' => 'mp-'.uniqid('', true),
            'payment_status' => PaymentAttempt::STATUS_PENDING,
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'amount_cents' => 500,
        ]);

        $u = $user->fresh();
        $this->assertInstanceOf(HasMany::class, $u->payments());
        $this->assertSame(1, $u->payments()->count());
    }

    public function test_conversations_relation_returns_belongs_to_many(): void
    {
        $user = User::factory()->create();
        $peer = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $user->conversations()->attach($conversation->id, ['read' => false]);
        $peer->conversations()->attach($conversation->id, ['read' => false]);

        $this->assertInstanceOf(BelongsToMany::class, $user->fresh()->conversations());
        $this->assertTrue($user->fresh()->conversations()->whereKey($conversation->id)->exists());
    }

    public function test_table_name_is_users(): void
    {
        $this->assertSame('users', (new User)->getTable());
    }
}
