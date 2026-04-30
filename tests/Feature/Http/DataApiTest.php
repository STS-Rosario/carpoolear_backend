<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Http\Controllers\Api\v1\DataController;
use STS\Models\ActiveUsersPerMonth;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\TripPoint;
use STS\Models\User;
use Tests\TestCase;

class DataApiTest extends TestCase
{
    public function test_trips_endpoint_returns_ok_with_trips_envelope(): void
    {
        $this->getJson('api/data/trips')
            ->assertOk()
            ->assertJsonStructure(['trips']);
    }

    public function test_trips_endpoint_groups_driver_trips_by_year_month(): void
    {
        $driver = User::factory()->create();

        Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => 0,
            'trip_date' => Carbon::parse('2027-02-10 08:00:00'),
            'total_seats' => 4,
        ]);

        $first = $this->getJson('api/data/trips')
            ->assertOk()
            ->json('trips.0');

        $this->assertNotNull($first);
        $this->assertSame('2027-02', $first['key']);
        $this->assertSame('2027', (string) $first['año']);
        $this->assertSame('02', (string) $first['mes']);
        $this->assertEquals(1, (int) $first['cantidad']);
        $this->assertEquals(4, (int) $first['asientos_ofrecidos_total']);
    }

    public function test_seats_endpoint_returns_ok_with_seats_envelope(): void
    {
        $this->getJson('api/data/seats')
            ->assertOk()
            ->assertJsonStructure(['seats']);
    }

    public function test_seats_endpoint_includes_request_state_labels(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => 0,
            'trip_date' => Carbon::parse('2027-03-05 12:00:00'),
        ]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $rows = $this->getJson('api/data/seats')
            ->assertOk()
            ->json('seats');

        $this->assertNotEmpty($rows);
        $match = collect($rows)->firstWhere('key', '2027-03');
        $this->assertNotNull($match);
        $this->assertSame(0, (int) $match['state']);
        $this->assertSame('pendiente', $match['estado']);
    }

    public function test_users_endpoint_returns_ok_with_users_envelope(): void
    {
        $this->getJson('api/data/users')
            ->assertOk()
            ->assertJsonStructure(['users']);
    }

    public function test_users_endpoint_groups_registrations_by_month(): void
    {
        $createdAt = Carbon::parse('2027-01-15 10:00:00');
        User::factory()->create(['created_at' => $createdAt]);

        $first = $this->getJson('api/data/users')
            ->assertOk()
            ->json('users.0');

        $this->assertNotNull($first);
        $this->assertSame('2027-01', $first['key']);
        $this->assertEquals(1, (int) $first['cantidad']);
    }

    public function test_monthly_users_endpoint_returns_active_user_series_shape(): void
    {
        $savedAt = Carbon::parse('2026-04-01 12:00:00', 'UTC');
        ActiveUsersPerMonth::query()->create([
            'year' => 2026,
            'month' => 4,
            'value' => 42,
            'saved_at' => $savedAt,
        ]);

        $payload = $this->getJson('api/data/monthlyusers')
            ->assertOk()
            ->json('monthly_users');

        $this->assertNotEmpty($payload);
        $row = collect($payload)->firstWhere('key', '2026-04');
        $this->assertNotNull($row);
        $this->assertSame(2026, (int) $row['año']);
        $this->assertSame(4, (int) $row['mes']);
        $this->assertSame(42, (int) $row['cantidad']);
        $this->assertArrayHasKey('saved_at', $row);
        $this->assertNotNull($row['saved_at']);
    }

    public function test_data_web_returns_aggregate_dashboard_sections(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => 0,
            'trip_date' => Carbon::parse('2028-05-20 09:00:00'),
            'total_seats' => 3,
        ]);

        TripPoint::factory()->rosario()->create(['trip_id' => $trip->id]);
        TripPoint::factory()->buenosAires()->create(['trip_id' => $trip->id]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);

        ActiveUsersPerMonth::query()->create([
            'year' => 2028,
            'month' => 5,
            'value' => 7,
            'saved_at' => Carbon::parse('2028-05-21 00:00:00', 'UTC'),
        ]);

        $this->get('/data-web')
            ->assertOk()
            ->assertJsonStructure([
                'usuarios',
                'viajes',
                'solicitudes',
                'frecuencia_origenes_posterior_ago_2017',
                'frecuencia_destinos_posterior_ago_2017',
                'frecuencia_origenes_destinos_posterior_ago_2017',
                'usuarios_activos',
            ]);
    }

    public function test_trips_seats_and_users_endpoints_return_500_with_expected_error_payload_when_query_fails(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('db failed'));

        $this->getJson('api/data/trips')
            ->assertStatus(500)
            ->assertJson(['error' => 'Error retrieving trips data']);

        $this->getJson('api/data/seats')
            ->assertStatus(500)
            ->assertJson(['error' => 'Error retrieving seats data']);

        $this->getJson('api/data/users')
            ->assertStatus(500)
            ->assertJson(['error' => 'Error retrieving users data']);
    }

    public function test_data_web_caps_frequency_sections_to_top_25_results(): void
    {
        $driver = User::factory()->create();
        $baseDate = Carbon::parse('2029-01-01 08:00:00');

        for ($i = 1; $i <= 30; $i++) {
            $trip = Trip::factory()->create([
                'user_id' => $driver->id,
                'is_passenger' => 0,
                'trip_date' => $baseDate->copy()->addDays($i),
                'total_seats' => 2,
            ]);

            TripPoint::query()->create([
                'trip_id' => $trip->id,
                'address' => "Origin {$i}",
                'description' => "Origin {$i}",
                'lat' => -31.40 + ($i / 1000),
                'lng' => -64.18 + ($i / 1000),
            ]);
            TripPoint::query()->create([
                'trip_id' => $trip->id,
                'address' => "Dest {$i}",
                'description' => "Dest {$i}",
                'lat' => -34.60 + ($i / 1000),
                'lng' => -58.38 + ($i / 1000),
            ]);
        }

        ActiveUsersPerMonth::query()->create([
            'year' => 2029,
            'month' => 1,
            'value' => 1,
            'saved_at' => Carbon::parse('2029-01-01 00:00:00', 'UTC'),
        ]);

        $payload = $this->get('/data-web')->assertOk()->json();

        $this->assertCount(25, $payload['frecuencia_origenes_posterior_ago_2017']);
        $this->assertCount(25, $payload['frecuencia_destinos_posterior_ago_2017']);
        $this->assertCount(25, $payload['frecuencia_origenes_destinos_posterior_ago_2017']);
        $this->assertArrayHasKey('key', $payload['usuarios_activos'][0]);
        $this->assertArrayHasKey('año', $payload['usuarios_activos'][0]);
        $this->assertArrayHasKey('mes', $payload['usuarios_activos'][0]);
        $this->assertArrayHasKey('cantidad', $payload['usuarios_activos'][0]);
        $this->assertArrayHasKey('saved_at', $payload['usuarios_activos'][0]);
    }

    public function test_more_data_uses_ranking_limit_of_50_and_returns_expected_envelopes(): void
    {
        DB::shouldReceive('select')
            ->withArgs(fn ($query, $bindings = []) => str_contains($query, 'LIMIT ?') && $bindings === [50])
            ->times(3)
            ->andReturn([]);

        $response = app(DataController::class)->moreData();
        $payload = $response->getData(true);

        $this->assertArrayHasKey('ranking_calificaciones', $payload);
        $this->assertArrayHasKey('ranking_conductores', $payload);
        $this->assertArrayHasKey('ranking_pasajeros', $payload);
    }
}
