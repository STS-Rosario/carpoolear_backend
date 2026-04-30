<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use STS\Helpers\IdentityValidationHelper;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class PassengerControllerIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('carpoolear.module_unaswered_message_limit', false);
        Config::set('carpoolear.module_user_request_limited', false);
        Config::set('carpoolear.module_trip_seats_payment', false);
        Config::set('carpoolear.module_send_full_trip_message', false);
    }

    private function enableStrictNewUserIdentityEnforcement(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2000-01-01',
        ]);
    }

    public function test_passenger_routes_return_unauthorized_when_not_authenticated(): void
    {
        $trip = Trip::factory()->create();
        $otherId = User::factory()->create()->id;

        $checks = [
            ['GET', "/api/trips/{$trip->id}/passengers"],
            ['GET', "/api/trips/{$trip->id}/requests"],
            ['GET', '/api/trips/requests'],
            ['GET', '/api/users/requests'],
            ['GET', '/api/users/payment-pending'],
            ['GET', '/api/trips/transactions'],
            ['POST', "/api/trips/{$trip->id}/requests"],
            ['POST', "/api/trips/{$trip->id}/requests/{$otherId}/cancel"],
            ['POST', "/api/trips/{$trip->id}/requests/{$otherId}/accept"],
            ['POST', "/api/trips/{$trip->id}/requests/{$otherId}/reject"],
            ['POST', "/api/trips/{$trip->id}/requests/{$otherId}/pay"],
        ];

        foreach ($checks as [$method, $uri]) {
            $this->json($method, $uri)
                ->assertUnauthorized()
                ->assertJsonPath('message', 'Unauthorized.');
        }
    }

    public function test_driver_lists_accepted_passengers_as_fractal_data(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $pax = User::factory()->create();
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $pax->id,
        ]);

        $this->actingAs($driver, 'api')
            ->getJson("/api/trips/{$trip->id}/passengers")
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.user.id', $pax->id);
    }

    public function test_driver_lists_pending_requests_for_trip(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $requester = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($driver, 'api')
            ->getJson("/api/trips/{$trip->id}/requests")
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.user.id', $requester->id);
    }

    public function test_new_request_returns_data_when_identity_allows_and_trip_is_open(): void
    {
        $driver = User::factory()->create(['autoaccept_requests' => false]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $requester = User::factory()->create(['identity_validated' => true]);

        $this->actingAs($requester, 'api')
            ->postJson("/api/trips/{$trip->id}/requests")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'trip_id', 'user_id']])
            ->assertJsonPath('data.trip_id', $trip->id)
            ->assertJsonPath('data.user_id', $requester->id);
    }

    public function test_new_request_returns_unprocessable_when_identity_required_and_user_not_validated(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $driver = User::factory()->create(['autoaccept_requests' => false]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $requester = User::factory()->create([
            'is_admin' => false,
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);

        $this->actingAs($requester, 'api')
            ->postJson("/api/trips/{$trip->id}/requests")
            ->assertUnprocessable()
            ->assertJsonPath('message', IdentityValidationHelper::identityValidationRequiredMessage());
    }

    public function test_new_request_returns_unprocessable_when_trip_is_expired(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->subDay(),
        ]);
        $requester = User::factory()->create(['identity_validated' => true]);

        $this->actingAs($requester, 'api')
            ->postJson("/api/trips/{$trip->id}/requests")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not create new request.');
    }

    public function test_new_request_second_post_returns_unprocessable_when_active_request_exists(): void
    {
        $driver = User::factory()->create(['autoaccept_requests' => false]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $requester = User::factory()->create(['identity_validated' => true]);

        $this->actingAs($requester, 'api')
            ->postJson("/api/trips/{$trip->id}/requests")
            ->assertOk();

        $this->actingAs($requester, 'api')
            ->postJson("/api/trips/{$trip->id}/requests")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not create new request.');
    }

    public function test_cancel_request_returns_data_envelope_when_passenger_cancels_pending(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($passenger, 'api')
            ->postJson("/api/trips/{$trip->id}/requests/{$passenger->id}/cancel")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'request_state']]);
    }

    public function test_cancel_request_returns_unprocessable_when_passenger_cannot_cancel(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        $stranger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($stranger, 'api')
            ->postJson("/api/trips/{$trip->id}/requests/{$passenger->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not cancel request.');
    }

    public function test_accept_request_returns_data_when_driver_has_capacity(): void
    {
        $driver = User::factory()->create(['identity_validated' => true]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 3,
        ]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($driver, 'api')
            ->postJson("/api/trips/{$trip->id}/requests/{$passenger->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.request_state', Passenger::STATE_ACCEPTED);
    }

    public function test_accept_request_returns_unprocessable_when_identity_required_and_driver_not_validated(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $driver = User::factory()->create([
            'is_admin' => false,
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        $trip = Trip::factory()->create(['user_id' => $driver->id, 'total_seats' => 3]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($driver, 'api')
            ->postJson("/api/trips/{$trip->id}/requests/{$passenger->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', IdentityValidationHelper::identityValidationRequiredMessage());
    }

    public function test_accept_request_returns_unprocessable_when_trip_has_no_seats(): void
    {
        $driver = User::factory()->create(['identity_validated' => true]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 1,
        ]);
        $occupant = User::factory()->create();
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $occupant->id,
        ]);
        $pending = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $pending->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($driver, 'api')
            ->postJson("/api/trips/{$trip->id}/requests/{$pending->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not accept request.')
            ->assertJsonPath('errors.error', 'not_seat_available');
    }

    public function test_reject_request_returns_data_when_driver_rejects_pending(): void
    {
        $driver = User::factory()->create(['identity_validated' => true]);
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($driver, 'api')
            ->postJson("/api/trips/{$trip->id}/requests/{$passenger->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.request_state', Passenger::STATE_REJECTED);
    }

    public function test_reject_request_returns_unprocessable_when_identity_required_and_driver_not_validated(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $driver = User::factory()->create([
            'is_admin' => false,
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($driver, 'api')
            ->postJson("/api/trips/{$trip->id}/requests/{$passenger->id}/reject")
            ->assertUnprocessable()
            ->assertJsonPath('message', IdentityValidationHelper::identityValidationRequiredMessage());
    }

    public function test_pay_request_returns_unprocessable_when_passenger_not_waiting_payment(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($driver, 'api')
            ->postJson("/api/trips/{$trip->id}/requests/{$passenger->id}/pay")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not accept request.')
            ->assertJsonPath('errors.error', 'not_valid_request');
    }

    public function test_transactions_returns_json_list_for_user_with_past_trip_payment_rows(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $pastTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subDay(),
        ]);
        $row = Passenger::factory()->aceptado()->create([
            'trip_id' => $pastTrip->id,
            'user_id' => $passengerUser->id,
        ]);
        $row->forceFill(['payment_status' => 'completed'])->saveQuietly();

        $this->actingAs($driver, 'api')
            ->getJson('/api/trips/transactions')
            ->assertOk()
            ->assertJsonFragment(['payment_status' => 'completed']);
    }

    public function test_all_requests_endpoint_returns_data_for_trip_owner(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $requester = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->actingAs($driver, 'api')
            ->getJson('/api/trips/requests')
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.user.id', $requester->id);
    }

    public function test_payment_pending_endpoint_returns_fractal_envelope(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('/api/users/payment-pending')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
