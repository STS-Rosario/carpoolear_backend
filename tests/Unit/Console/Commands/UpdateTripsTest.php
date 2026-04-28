<?php

namespace Tests\Unit\Console\Commands;

use Mockery;
use STS\Console\Commands\updateTrips;
use STS\Models\NodeGeo;
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
    }
}
