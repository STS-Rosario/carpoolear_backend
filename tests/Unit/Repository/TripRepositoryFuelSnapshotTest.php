<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Mockery;
use STS\Exceptions\TripCarFuelMissingException;
use STS\Models\Car;
use STS\Models\RouteCache;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\TripRepository;
use STS\Services\GeoService;
use STS\Services\MapboxDirectionsRouteService;
use STS\Services\MercadoPagoService;
use Tests\TestCase;

class TripRepositoryFuelSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Config::set('carpoolear.module_max_price_enabled', false);
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);
        Config::set('carpoolear.module_max_price_fuel_price', 1000);
        Config::set('carpoolear.module_max_price_fuel_price_nafta_premium', null);
        Config::set('carpoolear.module_max_price_fuel_price_gnc', 500);
        Config::set('carpoolear.module_max_price_price_variance_tolls', 0);
        Config::set('carpoolear.module_max_price_price_variance_max_extra', 15);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function geoService(): GeoService
    {
        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')->andReturn(false);
        $geoService->shouldReceive('hasExactlyOneStopInCostaAtlanticaZone')->andReturn(false);

        return $geoService;
    }

    private function realRepo(): TripRepository
    {
        return new TripRepository(
            $this->geoService(),
            Mockery::mock(MercadoPagoService::class),
            Mockery::mock(MapboxDirectionsRouteService::class)
        );
    }

    /**
     * @param  array<int, array>  $capturedTripInfoArgs  filled by reference with getTripInfo call args
     */
    private function partialRepo(array &$capturedTripInfoArgs): TripRepository
    {
        /** @var TripRepository $repo */
        $repo = Mockery::mock(
            TripRepository::class,
            [
                $this->geoService(),
                Mockery::mock(MercadoPagoService::class),
                Mockery::mock(MapboxDirectionsRouteService::class),
            ]
        )->makePartial();

        $repo->shouldReceive('getTripInfo')->andReturnUsing(function (...$args) use (&$capturedTripInfoArgs) {
            $capturedTripInfoArgs[] = $args;

            return ['status' => false];
        });
        $repo->shouldReceive('addPoints')->andReturnNull();
        $repo->shouldReceive('generateTripPath')->andReturnNull();
        $repo->shouldReceive('generateTripFriendVisibility')->andReturnNull();

        return $repo;
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'is_passenger' => 0,
            'from_town' => 'A',
            'to_town' => 'B',
            'trip_date' => Carbon::now()->addHour(),
            'total_seats' => 1,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'estimated_time' => '01:00',
            'distance' => 10,
            'co2' => 1,
            'description' => '',
            'mail_send' => false,
            'points' => [
                ['lat' => -34.6, 'lng' => -58.4, 'json_address' => ['id' => 9001, 'ciudad' => 'Origen']],
            ],
        ], $overrides);
    }

    private function points(): array
    {
        return [
            ['lat' => -34.6, 'lng' => -58.4, 'address' => 'A', 'json_address' => ['id' => 9001]],
            ['lat' => -32.9, 'lng' => -60.7, 'address' => 'B', 'json_address' => ['id' => 9002]],
        ];
    }

    public function test_create_with_car_writes_fuel_snapshot_and_prices_with_it(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'fuel_type' => 'gnc',
            'fuel_consumption' => 'high',
        ]);
        $captured = [];
        $repo = $this->partialRepo($captured);

        $trip = $repo->create($this->createPayload($user, ['car_id' => $car->id]))->fresh();

        $this->assertSame('gnc', $trip->fuel_type);
        $this->assertSame('high', $trip->fuel_consumption);
        $this->assertSame(500.0, $trip->fuel_price_per_unit);
        $this->assertSame(11.0, $trip->fuel_consumption_per_100);
        $this->assertSame('m3', $trip->fuel_unit);

        $this->assertCount(1, $captured);
        $fuelArg = $captured[0][3] ?? null;
        $this->assertIsArray($fuelArg);
        $this->assertSame('gnc', $fuelArg['fuel_type']);
        $this->assertSame(500.0, $fuelArg['price_per_unit']);
        $this->assertSame('m3', $fuelArg['unit']);
    }

    public function test_create_with_car_missing_fuel_throws_and_creates_nothing(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create(['user_id' => $user->id]);
        $captured = [];
        $repo = $this->partialRepo($captured);

        try {
            $repo->create($this->createPayload($user, ['car_id' => $car->id]));
            $this->fail('Expected TripCarFuelMissingException');
        } catch (TripCarFuelMissingException $e) {
            $this->assertSame(0, Trip::query()->where('user_id', $user->id)->count());
        }
    }

    public function test_create_with_car_missing_only_consumption_throws(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create(['user_id' => $user->id, 'fuel_type' => 'gnc']);
        $captured = [];
        $repo = $this->partialRepo($captured);

        $this->expectException(TripCarFuelMissingException::class);
        $repo->create($this->createPayload($user, ['car_id' => $car->id]));
    }

    public function test_create_without_car_leaves_snapshot_null_and_uses_legacy_pricing(): void
    {
        $user = User::factory()->create();
        $captured = [];
        $repo = $this->partialRepo($captured);

        $trip = $repo->create($this->createPayload($user, ['is_passenger' => 1]))->fresh();

        $this->assertNull($trip->fuel_type);
        $this->assertNull($trip->fuel_price_per_unit);
        $this->assertNull($captured[0][3] ?? null);
    }

    public function test_create_ignores_client_supplied_fuel_snapshot_fields(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'fuel_type' => 'diesel',
            'fuel_consumption' => 'low',
        ]);
        $captured = [];
        $repo = $this->partialRepo($captured);

        $trip = $repo->create($this->createPayload($user, [
            'car_id' => $car->id,
            'fuel_type' => 'gnc',
            'fuel_consumption' => 'high',
            'fuel_price_per_unit' => 1.0,
            'fuel_consumption_per_100' => 1.0,
            'fuel_unit' => 'm3',
        ]))->fresh();

        $this->assertSame('diesel', $trip->fuel_type);
        $this->assertSame('low', $trip->fuel_consumption);
        $this->assertSame('liter', $trip->fuel_unit);
        $this->assertSame(6.0, $trip->fuel_consumption_per_100);
    }

    public function test_create_ignores_client_supplied_fuel_fields_when_no_car(): void
    {
        $user = User::factory()->create();
        $captured = [];
        $repo = $this->partialRepo($captured);

        $trip = $repo->create($this->createPayload($user, [
            'is_passenger' => 1,
            'fuel_type' => 'gnc',
            'fuel_price_per_unit' => 1.0,
        ]))->fresh();

        $this->assertNull($trip->fuel_type);
        $this->assertNull($trip->fuel_price_per_unit);
    }

    public function test_update_keeps_original_fuel_snapshot(): void
    {
        $user = User::factory()->create();
        $newCar = Car::factory()->create([
            'user_id' => $user->id,
            'fuel_type' => 'diesel',
            'fuel_consumption' => 'low',
        ]);
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'fuel_type' => 'gnc',
            'fuel_consumption' => 'high',
            'fuel_price_per_unit' => 500.0,
            'fuel_consumption_per_100' => 11.0,
            'fuel_unit' => 'm3',
        ]);
        $captured = [];
        $repo = $this->partialRepo($captured);

        $updated = $repo->update($trip, [
            'description' => 'changed',
            'car_id' => $newCar->id,
            'fuel_type' => 'diesel',
            'fuel_consumption' => 'low',
            'fuel_price_per_unit' => 1.0,
            'fuel_consumption_per_100' => 6.0,
            'fuel_unit' => 'liter',
        ])->fresh();

        $this->assertSame('changed', $updated->description);
        $this->assertSame($newCar->id, $updated->car_id);
        $this->assertSame('gnc', $updated->fuel_type);
        $this->assertSame('high', $updated->fuel_consumption);
        $this->assertSame(500.0, $updated->fuel_price_per_unit);
        $this->assertSame(11.0, $updated->fuel_consumption_per_100);
        $this->assertSame('m3', $updated->fuel_unit);
    }

    public function test_update_with_points_reprices_using_the_trip_snapshot(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'fuel_type' => 'gnc',
            'fuel_consumption' => 'high',
            'fuel_price_per_unit' => 500.0,
            'fuel_consumption_per_100' => 11.0,
            'fuel_unit' => 'm3',
        ]);
        $captured = [];
        $repo = $this->partialRepo($captured);
        $repo->shouldReceive('deletePoints')->andReturnNull();

        $repo->update($trip, ['points' => $this->points()]);

        $fuelArg = $captured[0][3] ?? null;
        $this->assertIsArray($fuelArg);
        $this->assertSame('gnc', $fuelArg['fuel_type']);
        $this->assertSame(500.0, $fuelArg['price_per_unit']);
        $this->assertSame(11.0, $fuelArg['consumption_per_100']);
        $this->assertSame('m3', $fuelArg['unit']);
    }

    public function test_update_with_points_on_legacy_trip_uses_no_fuel_override(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $captured = [];
        $repo = $this->partialRepo($captured);
        $repo->shouldReceive('deletePoints')->andReturnNull();

        $repo->update($trip, ['points' => $this->points()]);

        $this->assertNull($captured[0][3] ?? null);
        $this->assertNull($trip->fresh()->fuel_type);
    }

    public function test_get_trip_info_without_fuel_defaults_to_legacy_nafta_premium_medium(): void
    {
        $method = new \ReflectionMethod(TripRepository::class, 'buildTripInfoMetricsForRoute');
        $method->setAccessible(true);

        $metrics = $method->invoke($this->realRepo(), 100000.0, 3600.0, $this->points());

        $this->assertSame('nafta_premium', $metrics['pricing_breakdown']['fuel_type']);
        $this->assertSame('medium', $metrics['pricing_breakdown']['fuel_consumption']);
        $this->assertSame('liter', $metrics['pricing_breakdown']['fuel_unit']);
        $this->assertSame(1000000, $metrics['pricing_breakdown']['fuel_cents']);
        $this->assertSame(1000000, $metrics['recommended_trip_price_cents']);
        $this->assertSame(1150000, $metrics['maximum_trip_price_cents']);
    }

    public function test_build_trip_info_metrics_uses_the_given_fuel_inputs(): void
    {
        $fuel = \STS\Helpers\FuelPricingResolver::resolve('gnc', 'high');
        $method = new \ReflectionMethod(TripRepository::class, 'buildTripInfoMetricsForRoute');
        $method->setAccessible(true);

        $metrics = $method->invoke($this->realRepo(), 100000.0, 3600.0, $this->points(), null, $fuel);

        $this->assertSame('gnc', $metrics['pricing_breakdown']['fuel_type']);
        $this->assertSame('m3', $metrics['pricing_breakdown']['fuel_unit']);
        $this->assertSame(550000, $metrics['pricing_breakdown']['fuel_cents']);
        $this->assertSame(550000, $metrics['recommended_trip_price_cents']);
        $this->assertSame(632500, $metrics['maximum_trip_price_cents']);
    }

    public function test_get_trip_info_with_fuel_reprices_cached_route_options(): void
    {
        Config::set('carpoolear.trip_route_cache_bypass', false);
        $points = $this->points();
        $legacyBreakdown = ['fuel_cents' => 1000000, 'total_cents' => 1000000];
        $option = [
            'route_index' => 0,
            'distance' => 100000.0,
            'duration' => 3600.0,
            'co2' => 15000.0,
            'route_needs_payment' => false,
            'recommended_trip_price_cents' => 1000000,
            'maximum_trip_price_cents' => 1150000,
            'pricing_breakdown' => $legacyBreakdown,
        ];
        $routeData = [
            'status' => true,
            'data' => array_merge($option, ['route_options' => [$option]]),
            'message' => 'Route found',
        ];
        unset($routeData['data']['route_index']);
        RouteCache::query()->create([
            'points' => $points,
            'route_data' => $routeData,
            'expires_at' => now()->addDay(),
        ]);

        $fuel = \STS\Helpers\FuelPricingResolver::resolve('gnc', 'high');
        $result = $this->realRepo()->getTripInfo($points, 0, null, $fuel);

        $this->assertTrue($result['status']);
        $this->assertSame(550000, $result['data']['recommended_trip_price_cents']);
        $this->assertSame(632500, $result['data']['maximum_trip_price_cents']);
        $this->assertSame('gnc', $result['data']['pricing_breakdown']['fuel_type']);
        $this->assertSame(550000, $result['data']['route_options'][0]['recommended_trip_price_cents']);
        $this->assertSame(0, $result['data']['route_options'][0]['route_index']);
    }

    public function test_get_trip_info_without_fuel_returns_cached_response_untouched(): void
    {
        Config::set('carpoolear.trip_route_cache_bypass', false);
        $points = $this->points();
        $routeData = [
            'status' => true,
            'data' => ['distance' => 5000.0, 'duration' => 900.0, 'recommended_trip_price_cents' => 4321],
            'message' => 'Route found',
        ];
        RouteCache::query()->create([
            'points' => $points,
            'route_data' => $routeData,
            'expires_at' => now()->addDay(),
        ]);

        $this->assertEquals($routeData, $this->realRepo()->getTripInfo($points));
    }
}
