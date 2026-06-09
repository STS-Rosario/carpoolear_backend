<?php

namespace Tests\Unit\Models;

use Database\Factories\PassengerFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ReflectionMethod;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class PassengerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRating(User $from, User $to, Trip $trip, array $overrides = []): Rating
    {
        $rating = Rating::factory()->create(array_merge([
            'trip_id' => $trip->id,
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'user_to_type' => 0,
            'user_to_state' => 0,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Test comment.',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => false,
            'voted_hash' => 'hash-'.uniqid('', true),
            'rate_at' => null,
        ], $overrides));

        if (array_key_exists('available', $overrides)) {
            $rating->forceFill(['available' => $overrides['available']])->saveQuietly();
        } else {
            $rating->forceFill(['available' => true])->saveQuietly();
        }

        return $rating->fresh();
    }

    public function test_new_factory_returns_passenger_factory(): void
    {
        $method = new ReflectionMethod(Passenger::class, 'newFactory');
        $method->setAccessible(true);
        $factory = $method->invoke(null);

        $this->assertInstanceOf(PassengerFactory::class, $factory);
        $this->assertInstanceOf(PassengerFactory::class, Passenger::factory());
    }

    public function test_fillable_lists_mass_assignment_columns(): void
    {
        $expected = [
            'user_id',
            'trip_id',
            'passenger_type',
            'request_state',
            'canceled_state',
        ];

        $this->assertSame($expected, (new Passenger)->getFillable());
    }

    public function test_hidden_is_empty_list(): void
    {
        $this->assertSame([], (new Passenger)->getHidden());
    }

    public function test_get_casts_includes_trip_id_integer_and_payment_info_array(): void
    {
        $expected = [
            'trip_id' => 'integer',
            'payment_info' => 'array',
        ];
        $casts = (new Passenger)->getCasts();
        foreach ($expected as $key => $type) {
            $this->assertSame($type, $casts[$key] ?? null, 'casts['.$key.']');
        }
    }

    public function test_rating_given_returns_has_many_and_filters_by_trip(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $tripOnPassenger = Trip::factory()->create(['user_id' => $driver->id]);
        $otherTrip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $tripOnPassenger->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $passenger->ratingGiven());

        $this->makeRating($passengerUser, $driver, $tripOnPassenger);
        $this->makeRating($passengerUser, $driver, $otherTrip);

        $this->assertSame(1, $passenger->fresh()->ratingGiven()->count());
    }

    public function test_rating_received_returns_has_many_and_filters_by_trip(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $tripOnPassenger = Trip::factory()->create(['user_id' => $driver->id]);
        $otherTrip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $tripOnPassenger->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $passenger->ratingReceived());

        $this->makeRating($driver, $passengerUser, $tripOnPassenger);
        $this->makeRating($driver, $passengerUser, $otherTrip);

        $this->assertSame(1, $passenger->fresh()->ratingReceived()->count());
    }

    public function test_user_and_trip_relations_return_belongs_to(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $passenger->user());
        $this->assertInstanceOf(BelongsTo::class, $passenger->trip());
        $this->assertTrue($passenger->user->is($passengerUser));
        $this->assertTrue($passenger->trip->is($trip));
    }

    public function test_trip_id_casts_to_integer(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
        ]);

        $this->assertIsInt($passenger->fresh()->trip_id);
        $this->assertSame($trip->id, $passenger->trip_id);
    }

    public function test_payment_info_casts_to_array_when_set_on_model(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
        ]);

        $payload = ['provider' => 'mp', 'preference_id' => 'pref-123'];
        $passenger->payment_info = $payload;
        $passenger->saveQuietly();

        $passenger = $passenger->fresh();
        $this->assertSame($payload, $passenger->payment_info);
        $this->assertIsArray($passenger->payment_info);
    }

    public function test_persists_request_passenger_type_and_canceled_state(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
            'request_state' => Passenger::STATE_ACCEPTED,
            'passenger_type' => Passenger::TYPE_CONDUCTOR,
            'canceled_state' => Passenger::CANCELED_DRIVER,
        ]);

        $passenger = $passenger->fresh();
        $this->assertSame(Passenger::STATE_ACCEPTED, $passenger->request_state);
        $this->assertSame(Passenger::TYPE_CONDUCTOR, $passenger->passenger_type);
        $this->assertSame(Passenger::CANCELED_DRIVER, $passenger->canceled_state);
    }

    public function test_state_type_and_cancel_constants(): void
    {
        $this->assertSame(0, Passenger::STATE_PENDING);
        $this->assertSame(1, Passenger::STATE_ACCEPTED);
        $this->assertSame(2, Passenger::STATE_REJECTED);
        $this->assertSame(3, Passenger::STATE_CANCELED);
        $this->assertSame(4, Passenger::STATE_WAITING_PAYMENT);

        $this->assertSame(0, Passenger::CANCELED_REQUEST);
        $this->assertSame(1, Passenger::CANCELED_DRIVER);
        $this->assertSame(2, Passenger::CANCELED_PASSENGER);
        $this->assertSame(3, Passenger::CANCELED_PASSENGER_WHILE_PAYING);
        $this->assertSame(4, Passenger::CANCELED_SYSTEM);

        $this->assertSame(0, Passenger::TYPE_CONDUCTOR);
        $this->assertSame(1, Passenger::TYPE_PASAJERO);
        $this->assertSame(2, Passenger::TYPE_CONDUCTORRECURRENTE);
    }

    public function test_is_eligible_for_rating(): void
    {
        $accepted = Passenger::factory()->make(['request_state' => Passenger::STATE_ACCEPTED]);
        $this->assertTrue($accepted->isEligibleForRating());

        $canceledByDriver = Passenger::factory()->make([
            'request_state' => Passenger::STATE_CANCELED,
            'canceled_state' => Passenger::CANCELED_DRIVER,
        ]);
        $this->assertTrue($canceledByDriver->isEligibleForRating());

        $canceledRequest = Passenger::factory()->make([
            'request_state' => Passenger::STATE_CANCELED,
            'canceled_state' => Passenger::CANCELED_REQUEST,
        ]);
        $this->assertFalse($canceledRequest->isEligibleForRating());

        $pending = Passenger::factory()->make(['request_state' => Passenger::STATE_PENDING]);
        $this->assertFalse($pending->isEligibleForRating());
    }

    public function test_table_name_is_trip_passengers(): void
    {
        $this->assertSame('trip_passengers', (new Passenger)->getTable());
    }

    public function test_casts_method_declares_trip_id_integer_and_payment_info_array(): void
    {
        $method = new ReflectionMethod(Passenger::class, 'casts');
        $method->setAccessible(true);
        $casts = $method->invoke(new Passenger);

        $this->assertSame([
            'trip_id' => 'integer',
            'payment_info' => 'array',
        ], $casts);
    }
}
