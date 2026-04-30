<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Models\ActiveUsersPerMonth;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\TripPoint;
use STS\Models\User;
use Tests\TestCase;

class DataApiTest extends TestCase
{
    use DatabaseTransactions;

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
}
