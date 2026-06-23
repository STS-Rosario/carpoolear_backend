<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use STS\Models\Passenger;
use STS\Models\Payment;
use STS\Models\PaymentAttempt;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\FriendsRepository;
use Tests\TestCase;

class TripTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_new_factory_returns_trip_factory(): void
    {
        $method = new \ReflectionMethod(Trip::class, 'newFactory');
        $method->setAccessible(true);
        $factory = $method->invoke(null);

        $this->assertInstanceOf(TripFactory::class, $factory);
        $this->assertInstanceOf(TripFactory::class, Trip::factory());
    }

    public function test_fillable_lists_mass_assignment_columns(): void
    {
        $expected = [
            'user_id',
            'from_town',
            'to_town',
            'punto_partida',
            'punto_llegada',
            'trip_date',
            'weekly_schedule',
            'weekly_schedule_time',
            'description',
            'total_seats',
            'friendship_type_id',
            'distance',
            'seat_price_cents',
            'recommended_trip_price_cents',
            'total_price',
            'estimated_time',
            'co2',
            'es_recurrente',
            'is_passenger',
            'mail_send',
            'return_trip_id',
            'enc_path',
            'car_id',
            'parent_trip_id',
            'allow_smoking',
            'allow_kids',
            'allow_animals',
            'rear_max_two_passengers',
            'state',
            'payment_id',
            'needs_sellado',
            'autoaccept_friends_requests',
        ];

        $this->assertSame($expected, (new Trip)->getFillable());
    }

    public function test_hidden_suppresses_enc_path(): void
    {
        $this->assertSame(['enc_path'], (new Trip)->getHidden());
    }

    public function test_appends_list_trip_computed_attributes(): void
    {
        $this->assertSame(
            ['passenger_count', 'seats_available', 'is_driver'],
            (new Trip)->getAppends()
        );
    }

    public function test_casts_include_scheduling_and_money_columns(): void
    {
        $expected = [
            'es_recurrente' => 'boolean',
            'weekly_schedule' => 'integer',
            'weekly_schedule_time' => 'datetime',
            'is_passenger' => 'boolean',
            'trip_date' => 'datetime',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
            'seat_price_cents' => 'integer',
            'recommended_trip_price_cents' => 'integer',
            'state' => 'string',
        ];

        $casts = (new Trip)->getCasts();
        foreach ($expected as $key => $type) {
            $this->assertSame($type, $casts[$key] ?? null, 'casts['.$key.']');
        }
    }

    public function test_to_array_includes_each_appended_accessor_key(): void
    {
        $trip = Trip::factory()->create();
        $array = $trip->fresh()->toArray();
        foreach ((new Trip)->getAppends() as $key) {
            $this->assertArrayHasKey($key, $array, 'Missing appended key: '.$key);
        }
    }

    public function test_parent_trip_relation_returns_has_one(): void
    {
        $this->assertInstanceOf(HasOne::class, (new Trip)->parentTrip());
    }

    public function test_ratings_relation_eager_loads_from_and_to(): void
    {
        $trip = Trip::factory()->create();
        $loads = $trip->fresh()->ratings()->getEagerLoads();

        $this->assertArrayHasKey('from', $loads);
        $this->assertArrayHasKey('to', $loads);
    }

    public function test_payments_relation_returns_has_many_and_counts_rows(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Payment::query()->create([
            'payment_id' => 'mp-'.uniqid('', true),
            'payment_status' => PaymentAttempt::STATUS_PENDING,
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'amount_cents' => 100,
        ]);

        $this->assertInstanceOf(HasMany::class, $trip->fresh()->payments());
        $this->assertSame(1, $trip->fresh()->payments()->count());
    }

    public function test_passenger_count_matches_accepted_passengers_excluding_driver(): void
    {
        $driver = User::factory()->create();
        $p1 = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 4,
        ]);

        Passenger::factory()->aceptado()->create(['user_id' => $p1->id, 'trip_id' => $trip->id]);
        Passenger::factory()->aceptado()->create(['user_id' => $driver->id, 'trip_id' => $trip->id]);

        $this->assertSame(1, $trip->fresh()->passenger_count);
    }

    public function test_is_pending_true_only_when_passenger_has_pending_or_waiting_payment(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->assertFalse($trip->fresh()->isPending($passengerUser));

        Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);
        $this->assertFalse($trip->fresh()->isPending($passengerUser));

        $trip->passenger()->where('user_id', $passengerUser->id)->update(['request_state' => Passenger::STATE_PENDING]);

        $this->assertTrue($trip->fresh()->isPending($passengerUser));
    }

    public function test_is_passenger_true_when_accepted_for_user(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->assertFalse($trip->fresh()->isPassenger($passengerUser));

        Passenger::factory()->aceptado()->create(['user_id' => $passengerUser->id, 'trip_id' => $trip->id]);

        $this->assertTrue($trip->fresh()->isPassenger($passengerUser));
    }

    public function test_passenger_pending_query_includes_only_pending_and_waiting_payment_states(): void
    {
        $driver = User::factory()->create();
        $uPending = User::factory()->create();
        $uWaiting = User::factory()->create();
        $uAccepted = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Passenger::factory()->create([
            'user_id' => $uPending->id,
            'trip_id' => $trip->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        Passenger::factory()->create([
            'user_id' => $uWaiting->id,
            'trip_id' => $trip->id,
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
        ]);
        Passenger::factory()->aceptado()->create([
            'user_id' => $uAccepted->id,
            'trip_id' => $trip->id,
        ]);

        $this->assertSame(2, $trip->fresh()->passengerPending()->count());
    }

    public function test_user_relation_returns_belongs_to_user(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Trip)->user());
    }

    public function test_check_friendship_self_branch_uses_loose_id_equality_for_friends_privacy(): void
    {
        // Mutation intent: `EqualToIdentical` on `$conductor->id == $user->id` (~300) — `===` fails for string id vs int id.
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $viewer = (object) ['id' => (string) $driver->id];

        $this->assertTrue($trip->fresh()->checkFriendship($viewer));
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($trip->fresh()->user->is($user));
    }

    public function test_state_helpers_and_setters(): void
    {
        $trip = Trip::factory()->create(['state' => Trip::STATE_READY]);

        $this->assertTrue($trip->fresh()->isReady());
        $this->assertFalse($trip->fresh()->isCanceled());

        $trip->fresh()->setStateCanceled()->save();
        $this->assertTrue($trip->fresh()->isCanceled());

        $trip->fresh()->setStateAwaitingPayment()->save();
        $this->assertTrue($trip->fresh()->isAwaitingPayment());

        $trip->fresh()->setStatePaymentFailed()->save();
        $this->assertTrue($trip->fresh()->isPaymentFailed());
    }

    public function test_set_state_ready_returns_same_model_for_fluent_chain(): void
    {
        // Mutation intent: `AlwaysReturnNull` on `return $this` in `setStateReady()` (~301).
        $trip = Trip::factory()->create(['state' => Trip::STATE_PENDING_PAYMENT]);
        $returned = $trip->fresh()->setStateReady();

        $this->assertInstanceOf(Trip::class, $returned);
        $this->assertTrue($returned->is($trip));
        $this->assertSame(Trip::STATE_READY, $returned->state);
    }

    public function test_secondary_trip_relations_return_expected_relation_objects(): void
    {
        // Mutation intent: `AlwaysReturnNull` on `userVisibility`, `car`, `routes`, `days`, `points`, `outbound`, `inbound`, `conversation`.
        $trip = Trip::factory()->create();

        $this->assertInstanceOf(HasMany::class, $trip->userVisibility());
        $this->assertInstanceOf(BelongsTo::class, $trip->car());
        $this->assertInstanceOf(BelongsToMany::class, $trip->routes());
        $this->assertInstanceOf(HasMany::class, $trip->days());
        $this->assertInstanceOf(HasMany::class, $trip->points());
        $this->assertInstanceOf(HasOne::class, $trip->outbound());
        $this->assertInstanceOf(BelongsTo::class, $trip->inbound());
        $this->assertInstanceOf(HasOne::class, $trip->conversation());
    }

    public function test_expired_false_when_weekly_schedule_non_zero_even_if_trip_date_past(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $trip = Trip::factory()->create([
            'weekly_schedule' => Trip::DAY_MONDAY,
            'trip_date' => '2026-01-01 08:00:00',
        ]);

        $this->assertFalse($trip->fresh()->expired());
    }

    public function test_expired_false_when_trip_date_null(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $trip = Trip::factory()->create([
            'weekly_schedule' => 0,
            'trip_date' => null,
        ]);

        $this->assertFalse($trip->fresh()->expired());
    }

    public function test_expired_true_when_single_date_trip_in_past(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $trip = Trip::factory()->create([
            'weekly_schedule' => 0,
            'trip_date' => '2026-08-10 10:00:00',
        ]);

        $this->assertTrue($trip->fresh()->expired());
    }

    public function test_seats_available_and_is_driver_appends(): void
    {
        $driver = User::factory()->create();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 5,
            'is_passenger' => false,
        ]);

        Passenger::factory()->aceptado()->create(['user_id' => $p1->id, 'trip_id' => $trip->id]);
        Passenger::factory()->aceptado()->create(['user_id' => $p2->id, 'trip_id' => $trip->id]);

        $trip = $trip->fresh();
        $this->assertSame(3, $trip->seats_available);
        $this->assertTrue($trip->is_driver);

        $passengerTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => true,
        ]);
        $this->assertFalse($passengerTrip->fresh()->is_driver);
    }

    public function test_to_array_hides_enc_path(): void
    {
        $trip = Trip::factory()->create(['enc_path' => 'secret-polyline-token']);
        $array = $trip->fresh()->toArray();

        $this->assertArrayNotHasKey('enc_path', $array);
    }

    public function test_core_constants(): void
    {
        $this->assertSame(0, Trip::FINALIZADO);
        $this->assertSame(1, Trip::ACTIVO);
        $this->assertSame(2, Trip::PRIVACY_PUBLIC);
        $this->assertSame(0, Trip::PRIVACY_FRIENDS);
        $this->assertSame(1, Trip::PRIVACY_FOF);
        $this->assertSame('ready', Trip::STATE_READY);
        $this->assertSame(1, Trip::DAY_MONDAY);
        $this->assertSame(64, Trip::DAY_SUNDAY);
    }

    public function test_soft_delete_marks_trashed(): void
    {
        $trip = Trip::factory()->create();
        $trip->delete();

        $this->assertTrue($trip->fresh()->trashed());
    }

    public function test_check_friendship_allows_driver_to_see_own_trip(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $this->assertTrue($trip->fresh()->checkFriendship($driver));
    }

    public function test_check_friendship_public_trip_allows_any_user(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
        ]);

        $this->assertTrue($trip->fresh()->checkFriendship($stranger));
    }

    public function test_check_friendship_friends_only_matches_accepted_friend_edge(): void
    {
        $friends = new FriendsRepository;
        $driver = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $friends->add($driver, $friend, User::FRIEND_ACCEPTED);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $trip = $trip->fresh();
        $this->assertTrue($trip->checkFriendship($friend));
        $this->assertFalse($trip->checkFriendship($stranger));
    }

    public function test_check_friendship_fof_allows_friend_of_friend_without_direct_friendship(): void
    {
        $friends = new FriendsRepository;
        $driver = User::factory()->create();
        $bridge = User::factory()->create();
        $friendOfFriend = User::factory()->create();
        $outsider = User::factory()->create();

        // Pivot direction matters: `friends()` is uid1 → uid2. FoF visibility tests use
        // bridge→driver then viewer→bridge so `friends.friends` reaches the conductor.
        $friends->add($bridge, $driver, User::FRIEND_ACCEPTED);
        $friends->add($friendOfFriend, $bridge, User::FRIEND_ACCEPTED);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FOF,
        ]);

        $trip = $trip->fresh();
        $this->assertTrue($trip->checkFriendship($friendOfFriend));
        $this->assertFalse($trip->checkFriendship($outsider));
    }
}
