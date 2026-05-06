<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Mockery;
use STS\Console\Commands\updateTrips;
use STS\Models\NodeGeo;
use STS\Models\Route;
use STS\Models\Trip;
use STS\Repository\RoutesRepository;
use STS\Services\Logic\RoutesManager;
use Tests\TestCase;

class UpdateTripsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_potential_node_returns_first_node_within_bounding_box(): void
    {
        NodeGeo::query()->insert([
            ['id' => 2001, 'name' => 'Out', 'lat' => -30.0, 'lng' => -60.0, 'type' => 'city'],
            ['id' => 2002, 'name' => 'In A', 'lat' => -34.58, 'lng' => -58.42, 'type' => 'city'],
            ['id' => 2003, 'name' => 'In B', 'lat' => -34.57, 'lng' => -58.41, 'type' => 'city'],
        ]);

        $command = new updateTrips(
            Mockery::mock(RoutesManager::class),
            Mockery::mock(RoutesRepository::class)
        );

        $n1 = new NodeGeo(['lat' => -34.60, 'lng' => -58.45]);
        $n2 = new NodeGeo(['lat' => -34.55, 'lng' => -58.40]);

        $result = $command->getPotentialNode($n1, $n2);

        $this->assertNotNull($result);
        $this->assertSame(2002, (int) $result->id);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new updateTrips(
            Mockery::mock(RoutesManager::class),
            Mockery::mock(RoutesRepository::class)
        );

        $this->assertSame('node:updateTrips', $command->getName());
        $this->assertStringContainsString('create and assign routes to old trips', $command->getDescription());
    }

    public function test_handle_skips_trip_without_points_and_keeps_route_id_null(): void
    {
        Event::fake([MessageLogged::class]);

        Trip::factory()->create([
            'trip_date' => '2018-01-10 08:00:00',
            'route_id' => null,
        ]);

        $this->app->instance(RoutesManager::class, Mockery::mock(RoutesManager::class));
        $this->app->instance(RoutesRepository::class, Mockery::mock(RoutesRepository::class));

        $this->artisan('node:updateTrips')
            ->expectsOutputToContain('No point')
            ->assertExitCode(0);

        $this->assertNull(Trip::query()->first()->route_id);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info' && $e->message === 'COMMAND updateTrips';
        });
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info' && str_starts_with($e->message, 'Trips: ');
        });
    }

    public function test_handle_skips_trip_with_single_point_without_crashing(): void
    {
        Event::fake([MessageLogged::class]);

        $trip = Trip::factory()->create([
            'trip_date' => '2018-01-10 08:00:00',
            'route_id' => null,
        ]);

        $trip->points()->create([
            'address' => 'Only one point',
            'lat' => -34.60,
            'lng' => -58.45,
        ]);

        $this->app->instance(RoutesManager::class, Mockery::mock(RoutesManager::class));
        $this->app->instance(RoutesRepository::class, Mockery::mock(RoutesRepository::class));

        $this->artisan('node:updateTrips')
            ->expectsOutputToContain('No point')
            ->assertExitCode(0);

        $this->assertNull($trip->fresh()->route_id);
    }

    public function test_handle_links_trip_to_existing_route_when_nodes_are_found(): void
    {
        $fromNode = NodeGeo::query()->create([
            'name' => 'From',
            'lat' => 12.34,
            'lng' => 56.78,
            'type' => 'city',
        ]);
        $toNode = NodeGeo::query()->create([
            'name' => 'To',
            'lat' => 12.60,
            'lng' => 57.10,
            'type' => 'city',
        ]);
        $route = Route::query()->create([
            'from_id' => $fromNode->id,
            'to_id' => $toNode->id,
        ]);

        $trip = Trip::factory()->create([
            'trip_date' => '2018-01-10 08:00:00',
            'route_id' => null,
        ]);
        $trip->points()->create([
            'address' => 'Start',
            'lat' => 12.34,
            'lng' => 56.78,
        ]);
        $trip->points()->create([
            'address' => 'End',
            'lat' => 12.60,
            'lng' => 57.10,
        ]);

        $this->app->instance(RoutesManager::class, Mockery::mock(RoutesManager::class));
        $this->app->instance(RoutesRepository::class, Mockery::mock(RoutesRepository::class));

        $this->artisan('node:updateTrips')->assertExitCode(0);

        $this->assertDatabaseHas('trip_routes', [
            'trip_id' => $trip->id,
            'route_id' => $route->id,
        ]);
        $this->assertSame(1, (int) $trip->fresh()->route_id);
        $this->assertSame(1, Route::query()->count());
    }

    public function test_handle_creates_route_when_matching_route_does_not_exist(): void
    {
        $fromNode = NodeGeo::query()->create([
            'name' => 'From New',
            'lat' => 22.11,
            'lng' => 33.44,
            'type' => 'city',
        ]);
        $toNode = NodeGeo::query()->create([
            'name' => 'To New',
            'lat' => 22.40,
            'lng' => 33.80,
            'type' => 'city',
        ]);
        $existingRoutesCount = Route::query()->count();

        $trip = Trip::factory()->create([
            'trip_date' => '2018-01-11 09:00:00',
            'route_id' => null,
        ]);
        $trip->points()->create([
            'address' => 'Start New',
            'lat' => 22.11,
            'lng' => 33.44,
        ]);
        $trip->points()->create([
            'address' => 'End New',
            'lat' => 22.40,
            'lng' => 33.80,
        ]);

        $this->app->instance(RoutesManager::class, Mockery::mock(RoutesManager::class));
        $this->app->instance(RoutesRepository::class, Mockery::mock(RoutesRepository::class));

        $this->artisan('node:updateTrips')->assertExitCode(0);

        $this->assertSame($existingRoutesCount + 1, Route::query()->count());
        $createdRoute = $trip->fresh()->routes()->first();
        $this->assertNotNull($createdRoute);
        $this->assertSame($fromNode->id, (int) $createdRoute->from_id);
        $this->assertSame($toNode->id, (int) $createdRoute->to_id);
        $this->assertDatabaseHas('trip_routes', [
            'trip_id' => $trip->id,
            'route_id' => $createdRoute->id,
        ]);
        $this->assertSame(1, (int) $trip->fresh()->route_id);
    }
}
